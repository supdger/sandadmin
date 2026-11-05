<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Error\ErrorResponder;
use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Domain\Exception\DomainException;
use Sandadmin\Domain\Exception\InvalidRequestException;
use Sandadmin\Domain\Exception\UnauthenticatedException;
use Sandadmin\Http\JsonErrorResponse;
use Sandadmin\Http\Middleware\AuthMiddleware;
use Sandadmin\Http\Middleware\ErrorMiddleware;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestIds;
use Sandadmin\Http\Timezones;
use Sandadmin\Infra\Db\Exception\InvalidQueryException;
use Sandadmin\Infra\Log\PsrErrorLogger;
use Sandadmin\Tests\Support\HttpStack;
use Sandadmin\Tests\Support\MapAuthenticator;
use Webman\Http\Request;
use Webman\Http\Response;

final class RequestPipelineTest extends TestCase
{
    public function testSequentialRequestsDoNotLeakContext(): void
    {
        $globals = array_keys($GLOBALS);
        $alice = new AuthenticatedUser('alice', 2, 'Asia/Shanghai');
        $bob = new AuthenticatedUser('bob', 9, 'UTC');
        $stack = HttpStack::create(new MapAuthenticator([
            'token-alice' => $alice,
            'token-bob' => $bob,
        ]));

        $first = self::request("Authorization: Bearer token-alice\r\nX-Request-Id: req-alice01\r\n");
        $second = self::request("Authorization: Bearer token-bob\r\nX-Request-Id: req-bob0002\r\nX-Timezone: Europe/Berlin\r\n");
        $seen = [];

        $run = static function (Request $request) use ($stack, &$seen): Response {
            return $stack->errors->process($request, static function (Request $current) use ($stack, &$seen): Response {
                return $stack->auth->process($current, static function (Request $authenticated) use ($stack, &$seen): Response {
                    $context = $stack->contexts->get($authenticated);
                    self::assertNotNull($context);
                    $seen[] = [$context->user?->id, $context->permissionVersion, $context->requestId, $context->timezone->getName()];

                    return new Response(204);
                });
            });
        };

        $firstResponse = $run($first);
        $secondResponse = $run($second);

        self::assertSame([
            ['alice', 2, 'req-alice01', 'Asia/Shanghai'],
            ['bob', 9, 'req-bob0002', 'Europe/Berlin'],
        ], $seen);
        self::assertSame('req-alice01', $firstResponse->getHeader('X-Request-Id'));
        self::assertSame('req-bob0002', $secondResponse->getHeader('X-Request-Id'));
        $aliceContext = $stack->contexts->get($first);
        $bobContext = $stack->contexts->get($second);
        self::assertInstanceOf(RequestContext::class, $aliceContext);
        self::assertInstanceOf(RequestContext::class, $bobContext);
        self::assertNotNull($aliceContext->user);
        self::assertNotNull($bobContext->user);
        self::assertSame('alice', $aliceContext->user->id);
        self::assertSame('bob', $bobContext->user->id);
        self::assertSame(2, $aliceContext->permissionVersion);
        self::assertSame('Asia/Shanghai', $aliceContext->timezone->getName());
        self::assertNull($stack->contexts->get(self::request('')));
        self::assertSame([], array_values(array_diff(array_keys($GLOBALS), $globals)));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testManyRequestsOnTheSameMiddlewareInstancesStayIsolated(): void
    {
        $users = [];
        for ($i = 0; $i < 25; $i++) {
            $users['token-' . $i] = new AuthenticatedUser('user-' . $i, $i, 'UTC');
        }
        $stack = HttpStack::create(new MapAuthenticator($users));
        for ($i = 0; $i < 25; $i++) {
            $request = self::request("Authorization: Bearer token-{$i}\r\nX-Request-Id: req-" . str_pad((string) $i, 8, '0', STR_PAD_LEFT) . "\r\n");
            $response = $stack->errors->process($request, static function (Request $current) use ($stack, $i): Response {
                return $stack->auth->process($current, static function (Request $authenticated) use ($stack, $i): Response {
                    $context = $stack->contexts->get($authenticated);
                    self::assertInstanceOf(RequestContext::class, $context);
                    self::assertNotNull($context->user);
                    self::assertSame('user-' . $i, $context->user->id);
                    self::assertSame($i, $context->permissionVersion);

                    return new Response(204);
                });
            });
            self::assertSame(204, $response->getStatusCode());
        }
    }

    public function testInterleavedFibersKeepTheContextOnTheirOwnRequest(): void
    {
        $stack = HttpStack::create(new MapAuthenticator([
            'token-a' => new AuthenticatedUser('user-a', 1, 'UTC'),
            'token-b' => new AuthenticatedUser('user-b', 2, 'Asia/Tokyo'),
        ]));
        $seen = [];
        $start = static function (string $token, string $requestId) use ($stack, &$seen): \Fiber {
            return new \Fiber(static function () use ($stack, $token, $requestId, &$seen): void {
                $request = self::request("Authorization: Bearer {$token}\r\nX-Request-Id: {$requestId}\r\n");
                $stack->errors->process($request, static function (Request $current) use ($stack, &$seen): Response {
                    return $stack->auth->process($current, static function (Request $authenticated) use ($stack, &$seen): Response {
                        $before = $stack->contexts->get($authenticated)?->user?->id;
                        \Fiber::suspend();
                        $after = $stack->contexts->get($authenticated)?->user?->id;
                        $seen[] = [$before, $after, $stack->contexts->get($authenticated)?->requestId];

                        return new Response(204);
                    });
                });
            });
        };

        $fiberA = $start('token-a', 'req-aaaaaaa');
        $fiberB = $start('token-b', 'req-bbbbbbb');
        $fiberA->start();
        $fiberB->start();
        $fiberA->resume();
        $fiberB->resume();

        self::assertSame([
            ['user-a', 'user-a', 'req-aaaaaaa'],
            ['user-b', 'user-b', 'req-bbbbbbb'],
        ], $seen);
    }

    public function testFailureBetweenRequestsDoesNotStickToTheNextRequest(): void
    {
        $stack = HttpStack::create(new MapAuthenticator([
            'token-bob' => new AuthenticatedUser('bob', 9, 'UTC'),
        ]));
        $failed = $stack->errors->process(self::request('X-Request-Id: req-failed1'), static function (Request $request): Response {
            throw new InvalidQueryException('sort 格式错误', ['sort' => 'string']);
        });
        self::assertSame(422, $failed->getStatusCode());

        $request = self::request("Authorization: Bearer token-bob\r\nX-Request-Id: req-bob0002\r\n");
        $stack->errors->process($request, static function (Request $current) use ($stack): Response {
            return $stack->auth->process($current, static function (Request $authenticated) use ($stack): Response {
                $context = $stack->contexts->get($authenticated);
                self::assertInstanceOf(RequestContext::class, $context);
                self::assertNotNull($context->user);
                self::assertSame('bob', $context->user->id);
                self::assertSame('req-bob0002', $context->requestId);

                return new Response(204);
            });
        });
    }

    public function testFoundationClassesHaveNoStaticProperties(): void
    {
        $classes = [
            RequestContext::class,
            AuthenticatedUser::class,
            RequestContextStore::class,
            RequestIds::class,
            Timezones::class,
            AuthMiddleware::class,
            ErrorMiddleware::class,
            ErrorResponder::class,
            PsrErrorLogger::class,
            JsonErrorResponse::class,
            DomainException::class,
            UnauthenticatedException::class,
            InvalidRequestException::class,
            InvalidQueryException::class,
        ];
        foreach ($classes as $class) {
            $static = array_filter(
                (new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_STATIC),
                static fn (\ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class,
            );
            self::assertSame([], $static, $class);
        }
    }

    private static function request(string $headers): Request
    {
        $block = $headers === '' ? '' : rtrim($headers, "\r\n") . "\r\n";

        return new Request("GET /api/notes HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }
}
