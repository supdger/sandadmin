<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Security\CorsConfig;
use Sandadmin\Application\Security\CsrfConfig;
use Sandadmin\Http\Middleware\CorsMiddleware;
use Webman\Http\Request;
use Webman\Http\Response;

final class CorsMiddlewareTest extends TestCase
{
    private const ALLOWED = 'https://admin.example.com';

    public function testNoOriginAddsNoCorsHeaders(): void
    {
        $response = $this->dispatch(self::request('GET', '/api/admin/v1/me'));

        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
        self::assertSame(204, $response->getStatusCode());
    }

    public function testAllowedOriginGetsAcaoAndVary(): void
    {
        $response = $this->dispatch(self::request('GET', '/api/admin/v1/me', ['Origin' => self::ALLOWED]));

        self::assertSame(self::ALLOWED, $response->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $response->getHeader('Vary'));
        self::assertNull($response->getHeader('Access-Control-Allow-Credentials'));
    }

    public function testForeignOriginGetsNoAcao(): void
    {
        $response = $this->dispatch(self::request('GET', '/api/admin/v1/me', ['Origin' => 'https://evil.example.com']));

        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $response->getHeader('Vary'));
    }

    public function testPreflightAnswersIndependently(): void
    {
        $downstream = false;
        $middleware = new CorsMiddleware(self::config(), CsrfConfig::fromArray([]));
        $response = $middleware->process(
            self::request('OPTIONS', '/api/admin/v1/auth/login', [
                'Origin' => self::ALLOWED,
                'Access-Control-Request-Method' => 'POST',
            ]),
            static function (Request $request) use (&$downstream): Response {
                $downstream = true;

                return new Response(200);
            },
        );

        self::assertFalse($downstream, '预检不得进入下游 handler');
        self::assertSame(204, $response->getStatusCode());
        self::assertSame(self::ALLOWED, $response->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('GET, POST, PUT, PATCH, DELETE', $response->getHeader('Access-Control-Allow-Methods'));
        self::assertSame('600', $response->getHeader('Access-Control-Max-Age'));
        self::assertNull($response->getHeader('Access-Control-Allow-Credentials'));
        // Allow-Headers 必含 CSRF 标记头
        $allowHeaders = $response->getHeader('Access-Control-Allow-Headers');
        self::assertIsString($allowHeaders);
        self::assertStringContainsString('X-Requested-With', $allowHeaders);
        self::assertStringContainsString('Authorization', $allowHeaders);
    }

    public function testPreflightMergesCustomMarkerName(): void
    {
        $middleware = new CorsMiddleware(
            self::config(),
            CsrfConfig::fromArray(['csrf' => ['required_header_name' => 'X-CSRF-Marker']]),
        );
        $response = $middleware->process(
            self::request('OPTIONS', '/api/admin/v1/auth/login', ['Origin' => self::ALLOWED]),
            static fn (Request $request): Response => new Response(200),
        );

        $allowHeaders = $response->getHeader('Access-Control-Allow-Headers');
        self::assertIsString($allowHeaders);
        self::assertStringContainsString('X-CSRF-Marker', $allowHeaders);
    }

    public function testForeignOriginPreflightWithoutAcao(): void
    {
        $response = $this->dispatch(self::request('OPTIONS', '/api/admin/v1/auth/login', ['Origin' => 'https://evil.example.com']));

        self::assertSame(204, $response->getStatusCode());
        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
        self::assertNull($response->getHeader('Access-Control-Allow-Methods'));
    }

    public function testNonV1PathPassesThrough(): void
    {
        $response = $this->dispatch(self::request('OPTIONS', '/api/dev/stream/sse', ['Origin' => self::ALLOWED]));

        // 非 v1：进入下游（204 桩响应），不加任何 CORS 头
        self::assertSame(204, $response->getStatusCode());
        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testEmptyWhitelistMeansSameOriginOnly(): void
    {
        $middleware = new CorsMiddleware(CorsConfig::fromArray([]), CsrfConfig::fromArray([]));
        $response = $middleware->process(
            self::request('GET', '/api/admin/v1/me', ['Origin' => self::ALLOWED]),
            static fn (Request $request): Response => new Response(204),
        );

        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
    }

    private function dispatch(Request $request): Response
    {
        return (new CorsMiddleware(self::config(), CsrfConfig::fromArray([])))
            ->process($request, static fn (Request $request): Response => new Response(204));
    }

    private static function config(): CorsConfig
    {
        return CorsConfig::fromArray(['cors' => ['allowed_origins' => [self::ALLOWED]]]);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function request(string $method, string $path, array $headers = []): Request
    {
        $block = '';
        foreach ($headers as $name => $value) {
            $block .= "{$name}: {$value}\r\n";
        }

        return new Request("{$method} {$path} HTTP/1.1\r\nHost: example.test\r\n{$block}\r\n");
    }
}
