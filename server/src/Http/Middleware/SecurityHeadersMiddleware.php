<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Application\Security\SecurityHeadersConfig;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 统一安全响应头（N3）：挂在全局 '' 键最外层，403/204 等短路响应也带头。
 * 同名头一律覆盖为配置值——下游若有差异化取值由配置项表达，而不是各写各的。
 * HSTS 仅 HTTPS 请求输出：HTTPS 判定只看连接对端 transport=ssl，
 * 不信 x-forwarded-proto 等转发头（可信代理解析属 N2 trusted_proxies 工作包）。
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private readonly SecurityHeadersConfig $config;

    public function __construct(?SecurityHeadersConfig $config = null)
    {
        $this->config = $config ?? SecurityHeadersConfig::fromConfig();
    }

    public function process(Request $request, callable $handler): Response
    {
        $response = ($handler)($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('安全响应头中间件下游必须返回 Response');
        }

        return $this->stamp($request, $response);
    }

    private function stamp(Request $request, Response $response): Response
    {
        $csp = $this->config->resolveCsp(self::readContentType($response));
        self::setHeader($response, 'X-Content-Type-Options', 'nosniff');
        self::setHeader($response, 'X-Frame-Options', 'DENY');
        self::setHeader($response, 'Referrer-Policy', $this->config->referrerPolicy);
        self::setHeader($response, $csp['name'], $csp['value']);
        self::setHeader($response, 'Cross-Origin-Opener-Policy', 'same-origin');
        self::setHeader($response, 'Permissions-Policy', $this->config->permissionsPolicy);
        if (self::isHttps($request)) {
            self::setHeader($response, 'Strict-Transport-Security', 'max-age=' . $this->config->hstsMaxAge);
        }

        return $response;
    }

    /**
     * Workerman Response 的头表按字面键存储，大小写敏感：下游若已写过小写
     * 同名头，withHeader 会并出双份。写前按大小写不敏感清掉旧键再落新值。
     */
    private static function setHeader(Response $response, string $name, string $value): void
    {
        foreach ($response->getHeaders() as $key => $item) {
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                $response->withoutHeader($key);
            }
        }
        $response->withHeader($name, $value);
    }

    private static function isHttps(Request $request): bool
    {
        return $request->connection?->transport === 'ssl';
    }

    private static function readContentType(Response $response): string
    {
        $value = $response->getHeader('Content-Type');
        if (is_string($value)) {
            return $value;
        }
        if (!is_array($value)) {
            return '';
        }
        $parts = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $parts[] = $item;
            }
        }

        return implode(',', $parts);
    }
}
