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
        $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', $this->config->referrerPolicy)
            ->withHeader($csp['name'], $csp['value'])
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->withHeader('Permissions-Policy', $this->config->permissionsPolicy);
        if (self::isHttps($request)) {
            $response->withHeader('Strict-Transport-Security', 'max-age=' . $this->config->hstsMaxAge);
        }

        return $response;
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
