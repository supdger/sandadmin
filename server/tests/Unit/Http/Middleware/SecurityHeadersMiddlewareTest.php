<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Security\SecurityHeadersConfig;
use Sandadmin\Http\Middleware\SecurityHeadersMiddleware;
use Webman\Http\Request;
use Webman\Http\Response;
use Workerman\Connection\TcpConnection;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testStampsAllHeadersOnPlainHttp(): void
    {
        $response = $this->dispatch(self::request('GET', '/api/admin/v1/me'), new Response(200));

        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeader('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->getHeader('Referrer-Policy'));
        self::assertSame('same-origin', $response->getHeader('Cross-Origin-Opener-Policy'));
        self::assertSame('camera=(), microphone=(), geolocation=()', $response->getHeader('Permissions-Policy'));
        // 灰度态：发 Report-Only 而非强制头；无 Content-Type 按页面策略
        self::assertSame("default-src 'self'", $response->getHeader('Content-Security-Policy-Report-Only'));
        self::assertNull($response->getHeader('Content-Security-Policy'));
        // HTTP 请求不输出 HSTS
        self::assertNull($response->getHeader('Strict-Transport-Security'));
    }

    public function testJsonResponseGetsApiCsp(): void
    {
        $response = $this->dispatch(
            self::request('GET', '/api/admin/v1/me'),
            new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], '{}'),
        );

        self::assertSame("default-src 'none'", $response->getHeader('Content-Security-Policy-Report-Only'));
    }

    public function testCspEnforceSwitchesHeaderName(): void
    {
        $middleware = new SecurityHeadersMiddleware(SecurityHeadersConfig::fromArray([
            'headers' => ['csp' => ['enforce' => true]],
        ]));
        $response = $middleware->process(self::request('GET', '/'), static fn (Request $request): Response => new Response(200));

        self::assertSame("default-src 'self'", $response->getHeader('Content-Security-Policy'));
        self::assertNull($response->getHeader('Content-Security-Policy-Report-Only'));
    }

    public function testHstsOnlyOnHttps(): void
    {
        $request = self::request('GET', '/api/admin/v1/me');
        $connection = (new \ReflectionClass(TcpConnection::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(TcpConnection::class, $connection);
        $connection->transport = 'ssl';
        $request->connection = $connection;

        $response = $this->dispatch($request, new Response(200));

        self::assertSame('max-age=15552000', $response->getHeader('Strict-Transport-Security'));
    }

    public function testConfigurableValues(): void
    {
        $middleware = new SecurityHeadersMiddleware(SecurityHeadersConfig::fromArray([
            'headers' => [
                'referrer_policy' => 'no-referrer',
                'hsts_max_age' => 1234,
                'csp' => ['enforce' => true, 'api' => "default-src 'none'; frame-ancestors 'none'"],
                'permissions_policy' => 'camera=()',
            ],
        ]));
        $request = self::request('GET', '/');
        $connection = (new \ReflectionClass(TcpConnection::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(TcpConnection::class, $connection);
        $connection->transport = 'ssl';
        $request->connection = $connection;

        $response = $middleware->process($request, static fn (Request $request): Response => new Response(200));

        self::assertSame('no-referrer', $response->getHeader('Referrer-Policy'));
        self::assertSame('max-age=1234', $response->getHeader('Strict-Transport-Security'));
        self::assertSame('camera=()', $response->getHeader('Permissions-Policy'));
    }

    public function testStampsShortCircuitResponses(): void
    {
        // 403 等下游短路响应也必须带头（SecurityHeaders 最外层挂载的意义）
        $response = $this->dispatch(self::request('GET', '/'), new Response(403));

        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeader('X-Frame-Options'));
    }

    public function testOverwritesConflictingDownstreamHeaders(): void
    {
        $response = $this->dispatch(
            self::request('GET', '/'),
            new Response(200, ['X-Frame-Options' => 'SAMEORIGIN', 'Referrer-Policy' => 'unsafe-url']),
        );

        self::assertSame('DENY', $response->getHeader('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->getHeader('Referrer-Policy'));
    }

    private function dispatch(Request $request, Response $downstream): Response
    {
        return (new SecurityHeadersMiddleware(SecurityHeadersConfig::fromArray([])))
            ->process($request, static fn (Request $request): Response => $downstream);
    }

    private static function request(string $method, string $path): Request
    {
        return new Request("{$method} {$path} HTTP/1.1\r\nHost: example.test\r\n\r\n");
    }
}
