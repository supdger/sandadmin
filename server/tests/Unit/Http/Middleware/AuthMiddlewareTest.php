<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Domain\Auth\SessionCookie;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Tests\Support\HttpStack;
use Sandadmin\Tests\Support\MapAuthenticator;
use Webman\Http\Request;
use Webman\Http\Response;

final class AuthMiddlewareTest extends TestCase
{
    public function testPopulatesRequestContextFromTheCurrentRequestOnly(): void
    {
        $user = new AuthenticatedUser('user-7', 4);
        $stack = HttpStack::create(new MapAuthenticator(['good-token' => $user]));
        $request = self::request("Cookie: sandadmin_session=good-token\r\nX-Request-Id: req-12345678\r\n");

        $response = $stack->auth->process($request, static function (Request $current) use ($stack): Response {
            $context = $stack->contexts->get($current);
            self::assertNotNull($context);
            self::assertSame('user-7', $context->user?->id);
            self::assertSame(4, $context->authzVersion());
            self::assertSame('req-12345678', $context->requestId);

            return new Response(204);
        });

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('4', $response->getHeader('X-Authz-Version'));
        $stored = $request->context[RequestContextStore::ATTRIBUTE] ?? null;
        self::assertSame($stack->contexts->get($request), $stored);
    }

    public function testIgnoresAuthorizationHeaderAndReadsOnlySessionCookie(): void
    {
        $stack = HttpStack::create(new MapAuthenticator(['good-token' => new AuthenticatedUser('user-7', 1)]));
        $request = self::request("Authorization: Bearer good-token\r\n");

        $this->expectException(\Sandadmin\Domain\Exception\UnauthenticatedException::class);
        $stack->auth->process($request, static fn (Request $request): Response => new Response(204));
    }

    public function testMissingAndInvalidCredentialsThrowBeforeTheHandler(): void
    {
        $stack = HttpStack::create(new MapAuthenticator([]));
        $called = false;
        $handler = static function (Request $request) use (&$called): Response {
            $called = true;

            return new Response(204);
        };

        $cases = [
            '',
            'Cookie: other=1\r\n',
            'Cookie: sandadmin_session=\r\n',
            'Cookie: sandadmin_session=%20%20\r\n',
        ];
        foreach ($cases as $headers) {
            try {
                $stack->auth->process(self::request($headers), $handler);
                self::fail('应当拒绝未认证请求');
            } catch (\Sandadmin\Domain\Exception\UnauthenticatedException $e) {
                self::assertSame('请先登录', $e->getMessage());
            }
        }
        self::assertFalse($called);
    }

    public function testSessionCookieNameMatchesTheContract(): void
    {
        self::assertSame('sandadmin_session', SessionCookie::NAME);
        self::assertSame('/', SessionCookie::PATH);
        self::assertSame('Lax', SessionCookie::SAME_SITE);
    }

    private static function request(string $headers): Request
    {
        $block = $headers === '' ? '' : rtrim($headers, "\r\n") . "\r\n";

        return new Request("GET /api/admin/v1/me HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }
}
