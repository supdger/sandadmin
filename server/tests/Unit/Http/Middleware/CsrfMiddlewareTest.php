<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Security\CsrfConfig;
use Sandadmin\Http\Middleware\CsrfMiddleware;
use Webman\Context;
use Webman\Http\Request;
use Webman\Http\Response;

final class CsrfMiddlewareTest extends TestCase
{
    private const SITE = 'https://admin.example.com';

    public function testGetPassesThroughWithoutChecks(): void
    {
        $response = $this->dispatch(self::request('GET', '/api/admin/v1/me'), self::config());

        self::assertSame(204, $response->getStatusCode());
    }

    public function testPostWithMatchingOriginAndMarkerPasses(): void
    {
        $request = self::request('POST', '/api/admin/v1/auth/login', [
            'Origin' => self::SITE,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertSame(204, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testPublicEndpointsAreCheckedToo(): void
    {
        // 公开端点（登录/登出/改密）同样校验：本中间件不区分端点
        $request = self::request('POST', '/api/admin/v1/auth/logout', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $response = $this->dispatch($request, self::config());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF_REJECTED', $this->readCode($response));
    }

    public function testLiteralNullOriginRejected(): void
    {
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'Origin' => 'null',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertSame(403, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testMissingOriginFallsBackToReferer(): void
    {
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'Referer' => self::SITE . '/system/user',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertSame(204, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testCrossSiteRefererRejected(): void
    {
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'Referer' => 'https://evil.example.com/page',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertSame(403, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testMissingBothOriginAndRefererRejected(): void
    {
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertSame(403, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testMismatchedOriginRejected(): void
    {
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'Origin' => 'https://evil.example.com',
            'Referer' => self::SITE . '/ok',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        // Origin 存在即以 Origin 为准，不回落 Referer
        self::assertSame(403, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testMissingMarkerHeaderRejected(): void
    {
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'Origin' => self::SITE,
        ]);
        $response = $this->dispatch($request, self::config());

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF_REJECTED', $this->readCode($response));
    }

    public function testMarkerValueEnforcedWhenConfigured(): void
    {
        $config = CsrfConfig::fromArray([
            'site_origin' => self::SITE,
            'csrf' => ['required_header_value' => 'fetch'],
        ]);
        $wrong = self::request('POST', '/api/admin/v1/me/profile', [
            'Origin' => self::SITE,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $right = self::request('POST', '/api/admin/v1/me/profile', [
            'Origin' => self::SITE,
            'X-Requested-With' => 'fetch',
        ]);

        self::assertSame(403, $this->dispatch($wrong, $config)->getStatusCode());
        self::assertSame(204, $this->dispatch($right, $config)->getStatusCode());
    }

    public function testNonV1PathPassesThrough(): void
    {
        $request = self::request('POST', '/core/login/index', []);

        self::assertSame(204, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testV1PrefixDoesNotOvermatch(): void
    {
        // /api/admin/v1x 不是 v1 组：必须等长或以 /api/admin/v1/ 开头
        $request = self::request('POST', '/api/admin/v1x/users', []);
        $root = self::request('POST', '/api/admin/v1', []);

        self::assertSame(204, $this->dispatch($request, self::config())->getStatusCode());
        self::assertSame(403, $this->dispatch($root, self::config())->getStatusCode());
    }

    public function testUserinfoOriginRejected(): void
    {
        // parse_url 会把 https://evil.com@host 的 userinfo 吞掉，严格形态必须拦
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'Origin' => 'https://evil.com@' . parse_url(self::SITE, PHP_URL_HOST),
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertSame(403, $this->dispatch($request, self::config())->getStatusCode());
    }

    public function testNormalizedOriginFormsStillMatch(): void
    {
        // 尾斜杠、大小写、显式默认端口归一后按规范值比对
        foreach (['https://admin.example.com/', 'HTTPS://ADMIN.EXAMPLE.COM', 'https://admin.example.com:443'] as $origin) {
            $request = self::request('POST', '/api/admin/v1/me/profile', [
                'Origin' => $origin,
                'X-Requested-With' => 'XMLHttpRequest',
            ]);
            self::assertSame(204, $this->dispatch($request, self::config())->getStatusCode(), "origin={$origin}");
        }
    }

    public function testMarkerValueConfigIsTrimmed(): void
    {
        $config = CsrfConfig::fromArray([
            'site_origin' => self::SITE,
            'csrf' => ['required_header_value' => "  fetch\t"],
        ]);
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'Origin' => self::SITE,
            'X-Requested-With' => 'fetch',
        ]);

        self::assertSame('fetch', $config->requiredHeaderValue);
        self::assertSame(204, $this->dispatch($request, $config)->getStatusCode());
    }

    public function testInvalidSiteOriginConfigIsDiagnosable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CsrfConfig::fromArray(['site_origin' => 'https://admin.example.com/dashboard']);
    }

    public function testUserinfoSiteOriginConfigThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CsrfConfig::fromArray(['site_origin' => 'https://u:p@admin.example.com']);
    }

    public function testSiteOriginFoldsDefaultPort(): void
    {
        $config = CsrfConfig::fromArray(['site_origin' => 'https://admin.example.com:443/']);

        self::assertSame('https://admin.example.com', $config->siteOrigin);
    }

    public function testNonHttpSiteOriginThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CsrfConfig::fromArray(['site_origin' => 'ftp://admin.example.com']);
    }

    public function testWildcardSiteOriginThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CsrfConfig::fromArray(['site_origin' => 'https://*.example.com']);
    }

    public function testUnconfiguredSiteOriginSkipsOriginCheckButKeepsMarker(): void
    {
        $config = CsrfConfig::fromArray([]); // site_origin 未配置
        $withMarker = self::request('POST', '/api/admin/v1/me/profile', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $withoutMarker = self::request('POST', '/api/admin/v1/me/profile', []);

        self::assertSame(204, $this->dispatch($withMarker, $config)->getStatusCode());
        self::assertSame(403, $this->dispatch($withoutMarker, $config)->getStatusCode());
    }

    public function testRejectionCarriesContractEnvelopeAndRequestId(): void
    {
        $request = self::request('POST', '/api/admin/v1/me/profile', [
            'X-Request-Id' => 'req-deadbeef',
        ]);
        $response = $this->dispatch($request, self::config());

        self::assertSame(403, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        self::assertIsArray($body);
        self::assertSame('CSRF_REJECTED', $body['code'] ?? null);
        self::assertSame('req-deadbeef', $body['request_id'] ?? null);
        self::assertSame('req-deadbeef', $response->getHeader('X-Request-Id'));
    }

    private function dispatch(Request $request, CsrfConfig $config): Response
    {
        // CsrfMiddleware 往请求上下文写 request_id：每次模拟请求先 reset/destroy，
        // 否则非纤程上下文会把上一个用例的 request_id 带进本用例
        Context::reset();
        try {
            return (new CsrfMiddleware($config))
                ->process($request, static fn (Request $request): Response => new Response(204));
        } finally {
            Context::destroy();
        }
    }

    private static function config(): CsrfConfig
    {
        return CsrfConfig::fromArray(['site_origin' => self::SITE]);
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

        return new Request("{$method} {$path} HTTP/1.1\r\nHost: admin.example.test\r\n{$block}\r\n");
    }

    private function readCode(Response $response): ?string
    {
        $body = json_decode($response->rawBody(), true);

        return is_array($body) && is_string($body['code'] ?? null) ? $body['code'] : null;
    }
}
