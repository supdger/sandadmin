<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Auth\AuthenticatedUser;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Tests\Support\HttpStack;
use Sandadmin\Tests\Support\MapAuthenticator;
use Webman\Http\Request;
use Webman\Http\Response;

final class AuthMiddlewareTest extends TestCase
{
    public function testPopulatesRequestContextFromTheCurrentRequestOnly(): void
    {
        $user = new AuthenticatedUser('user-7', 4, 'Asia/Shanghai');
        $stack = HttpStack::create(new MapAuthenticator(['good-token' => $user]));
        $request = self::request("Authorization: Bearer good-token\r\nX-Request-Id: req-12345678\r\n");

        $response = $stack->auth->process($request, static function (Request $current) use ($stack): Response {
            $context = $stack->contexts->get($current);
            self::assertNotNull($context);
            self::assertSame('user-7', $context->user?->id);
            self::assertSame(4, $context->permissionVersion);
            self::assertSame('req-12345678', $context->requestId);
            self::assertSame('Asia/Shanghai', $context->timezone->getName());

            return new Response(204);
        });

        self::assertSame(204, $response->getStatusCode());
        $stored = $request->context[RequestContextStore::ATTRIBUTE] ?? null;
        self::assertSame($stack->contexts->get($request), $stored);
    }

    public function testHeaderTimezoneOverridesTheUserTimezone(): void
    {
        $stack = HttpStack::create(new MapAuthenticator([
            'good-token' => new AuthenticatedUser('user-7', 1, 'Asia/Shanghai'),
        ]));
        $request = self::request("Authorization: Bearer good-token\r\nX-Timezone: Europe/Berlin\r\n");
        $stack->auth->process($request, static function (Request $current) use ($stack): Response {
            self::assertSame('Europe/Berlin', $stack->contexts->get($current)?->timezone->getName());

            return new Response(204);
        });
    }

    public function testMissingAndInvalidCredentialsThrowBeforeTheHandler(): void
    {
        $stack = HttpStack::create(new MapAuthenticator([]));
        $called = false;
        $handler = static function (Request $request) use (&$called): Response {
            $called = true;

            return new Response(204);
        };

        foreach (['', "Authorization: Basic aaa\r\n", "Authorization: Bearer \r\n", "Authorization: Bearer bad token\r\n"] as $headers) {
            try {
                $stack->auth->process(self::request($headers), $handler);
                self::fail('应当拒绝未认证请求');
            } catch (\Sandadmin\Domain\Exception\UnauthenticatedException $e) {
                self::assertSame('未登录', $e->getMessage());
            }
        }
        self::assertFalse($called);
    }

    private static function request(string $headers): Request
    {
        $block = $headers === '' ? '' : rtrim($headers, "\r\n") . "\r\n";

        return new Request("GET /api/notes HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }
}
