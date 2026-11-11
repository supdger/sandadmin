<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Error\ErrorResponder;
use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Domain\Auth\SessionCookie;
use Sandadmin\Domain\Exception\DomainException;
use Sandadmin\Domain\Exception\SessionExpiredException;
use Sandadmin\Domain\Exception\UnauthenticatedException;
use Sandadmin\Http\JsonErrorResponse;
use Sandadmin\Http\JsonSuccessResponse;
use Sandadmin\Http\Middleware\AuthMiddleware;
use Sandadmin\Http\Middleware\ErrorMiddleware;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestIds;
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
        $alice = new AuthenticatedUser('alice', 2);
        $bob = new AuthenticatedUser('bob', 9);
        $stack = HttpStack::create(new MapAuthenticator([
            'token-alice' => $alice,
            'token-bob' => $bob,
        ]));

        $first = self::request("Cookie: sandadmin_session=token-alice\r\nX-Request-Id: req-alice01\r\n");
        $second = self::request("Cookie: sandadmin_session=token-bob\r\nX-Request-Id: req-bob0002\r\nX-Timezone: Europe/Berlin\r\n");
        $seen = [];

        $run = static function (Request $request) use ($stack, &$seen): Response {
            return $stack->errors->process($request, static function (Request $current) use ($stack, &$seen): Response {
                return $stack->auth->process($current, static function (Request $authenticated) use ($stack, &$seen): Response {
                    $context = $stack->contexts->get($authenticated);
                    self::assertNotNull($context);
                    $seen[] = [$context->user?->id, $context->authzVersion(), $context->requestId];

                    return new Response(204);
                });
            });
        };

        $firstResponse = $run($first);
        $secondResponse = $run($second);

        // X-Timezone 请求头不再参与时区判定（统一用系统设置时区），也不影响上下文隔离
        self::assertSame([
            ['alice', 2, 'req-alice01'],
            ['bob', 9, 'req-bob0002'],
        ], $seen);
        self::assertSame('req-alice01', $firstResponse->getHeader('X-Request-Id'));
        self::assertSame('req-bob0002', $secondResponse->getHeader('X-Request-Id'));
        self::assertSame('2', $firstResponse->getHeader('X-Authz-Version'));
        self::assertSame('9', $secondResponse->getHeader('X-Authz-Version'));
        $aliceContext = $stack->contexts->get($first);
        $bobContext = $stack->contexts->get($second);
        self::assertInstanceOf(RequestContext::class, $aliceContext);
        self::assertInstanceOf(RequestContext::class, $bobContext);
        self::assertNotNull($aliceContext->user);
        self::assertNotNull($bobContext->user);
        self::assertSame('alice', $aliceContext->user->id);
        self::assertSame('bob', $bobContext->user->id);
        self::assertNull($stack->contexts->get(self::request('')));
        self::assertSame([], array_values(array_diff(array_keys($GLOBALS), $globals)));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testManyRequestsOnTheSameMiddlewareInstancesStayIsolated(): void
    {
        $users = [];
        for ($i = 0; $i < 25; $i++) {
            $users['token-' . $i] = new AuthenticatedUser('user-' . $i, $i + 1);
        }
        $stack = HttpStack::create(new MapAuthenticator($users));
        for ($i = 0; $i < 25; $i++) {
            $request = self::request("Cookie: sandadmin_session=token-{$i}\r\nX-Request-Id: req-" . str_pad((string) $i, 8, '0', STR_PAD_LEFT) . "\r\n");
            $response = $stack->errors->process($request, static function (Request $current) use ($stack, $i): Response {
                return $stack->auth->process($current, static function (Request $authenticated) use ($stack, $i): Response {
                    $context = $stack->contexts->get($authenticated);
                    self::assertInstanceOf(RequestContext::class, $context);
                    self::assertNotNull($context->user);
                    self::assertSame('user-' . $i, $context->user->id);
                    self::assertSame($i + 1, $context->authzVersion());

                    return new Response(204);
                });
            });
            self::assertSame(204, $response->getStatusCode());
        }
    }

    public function testInterleavedFibersKeepTheContextOnTheirOwnRequest(): void
    {
        $stack = HttpStack::create(new MapAuthenticator([
            'token-a' => new AuthenticatedUser('user-a', 1),
            'token-b' => new AuthenticatedUser('user-b', 2),
        ]));
        $seen = [];
        $start = static function (string $token, string $requestId) use ($stack, &$seen): \Fiber {
            return new \Fiber(static function () use ($stack, $token, $requestId, &$seen): void {
                $request = self::request("Cookie: sandadmin_session={$token}\r\nX-Request-Id: {$requestId}\r\n");
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
            'token-bob' => new AuthenticatedUser('bob', 9),
        ]));
        $failed = $stack->errors->process(self::request('X-Request-Id: req-failed1'), static function (Request $request): Response {
            throw new InvalidQueryException('sort 格式错误', ['sort' => '只能是登记过的字段']);
        });
        self::assertSame(422, $failed->getStatusCode());
        $failedBody = json_decode($failed->rawBody(), true);
        self::assertIsArray($failedBody);
        self::assertSame('VALIDATION_FAILED', $failedBody['code'] ?? null);

        $request = self::request("Cookie: sandadmin_session=token-bob\r\nX-Request-Id: req-bob0002\r\n");
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

    public function testSuccessEnvelopeCarriesPayloadAndRequestId(): void
    {
        $json = new JsonSuccessResponse();
        $response = $json->fromPayload(['items' => [], 'total' => 0], 'req-12345678');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"data":{"items":[],"total":0},"request_id":"req-12345678"}', $response->rawBody());
        self::assertSame('req-12345678', $response->getHeader('X-Request-Id'));
        self::assertSame('application/json; charset=utf-8', $response->getHeader('Content-Type'));
    }

    public function testExpiredSessionMapsToContractErrorThroughTheStack(): void
    {
        $stack = HttpStack::create(new class implements \Sandadmin\Domain\Auth\Authenticator {
            public function authenticate(string $token): AuthenticatedUser
            {
                throw new SessionExpiredException();
            }
        });
        $response = $stack->errors->process(
            self::request("Cookie: sandadmin_session=revoked\r\nX-Request-Id: req-expired1\r\n"),
            fn (Request $request): Response => $stack->auth->process($request, static fn (Request $request): Response => new Response(204)),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            ['code' => 'SESSION_EXPIRED', 'message' => '登录已过期，请重新登录', 'fields' => [], 'request_id' => 'req-expired1'],
            json_decode($response->rawBody(), true),
        );
    }

    public function testFoundationClassesHaveNoStaticProperties(): void
    {
        $classes = [
            RequestContext::class,
            AuthenticatedUser::class,
            SessionCookie::class,
            RequestContextStore::class,
            RequestIds::class,
            AuthMiddleware::class,
            ErrorMiddleware::class,
            ErrorResponder::class,
            PsrErrorLogger::class,
            JsonErrorResponse::class,
            JsonSuccessResponse::class,
            DomainException::class,
            UnauthenticatedException::class,
            SessionExpiredException::class,
            \Sandadmin\Domain\Exception\InvalidRequestException::class,
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

        return new Request("GET /api/admin/v1/me HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }
}
