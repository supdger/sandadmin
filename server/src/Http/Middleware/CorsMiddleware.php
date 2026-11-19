<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Application\Security\CorsConfig;
use Sandadmin\Application\Security\CsrfConfig;
use Sandadmin\Http\RequestHeaders;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * v1 组跨域白名单（A-5）：只在 Origin 精确命中白名单时签发 ACAO，
 * 永不输出 Access-Control-Allow-Credentials；OPTIONS 预检独立应答、不进下游。
 * 白名单外的预检同样 204 但不带 ACAO——状态成功、浏览器因缺 ACAO 拦截实际请求，
 * 避免把预检失败当错误页暴露。写操作放行与否仍由 CsrfMiddleware 的
 * Origin===site_origin 判定兜底（CORS 白名单≠CSRF 放行）。
 */
final class CorsMiddleware implements MiddlewareInterface
{
    private const V1_PREFIX = '/api/admin/v1/';

    private const V1_ROOT = '/api/admin/v1';

    private readonly CorsConfig $config;

    private readonly CsrfConfig $csrfConfig;

    private readonly RequestHeaders $headers;

    public function __construct(?CorsConfig $config = null, ?CsrfConfig $csrfConfig = null)
    {
        $this->config = $config ?? CorsConfig::fromConfig();
        $this->csrfConfig = $csrfConfig ?? CsrfConfig::fromConfig();
        $this->headers = new RequestHeaders();
    }

    public function process(Request $request, callable $handler): Response
    {
        $origin = self::isV1Path($request->path())
            ? $this->headers->optional($request, 'origin')
            : null;
        $matched = $origin !== null ? $this->config->match($origin) : null;
        if ($matched !== null && strtoupper($request->method()) === 'OPTIONS') {
            return $this->answerPreflight($matched);
        }
        $isPreflightDenied = $origin !== null && $matched === null
            && strtoupper($request->method()) === 'OPTIONS';
        if ($isPreflightDenied) {
            return new Response(204, ['Vary' => 'Origin']);
        }
        $response = ($handler)($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('跨域中间件下游必须返回 Response');
        }
        if ($origin === null) {
            return $response;
        }
        // Origin 在场即按 Vary 声明：避免共享缓存把带/不带 ACAO 的响应混发给不同源。
        $response = self::varyOn($response);

        return $matched !== null
            ? $response->withHeader('Access-Control-Allow-Origin', $matched)
            : $response;
    }

    private static function isV1Path(string $path): bool
    {
        return $path === self::V1_ROOT || str_starts_with($path, self::V1_PREFIX);
    }

    private function answerPreflight(string $matched): Response
    {
        return new Response(204, [
            'Access-Control-Allow-Origin' => $matched,
            'Access-Control-Allow-Methods' => implode(', ', $this->config->allowedMethods),
            'Access-Control-Allow-Headers' => implode(', ', $this->config->allowHeaders($this->csrfConfig->requiredHeaderName)),
            'Access-Control-Max-Age' => (string) $this->config->maxAge,
            'Vary' => 'Origin',
        ]);
    }

    /** 已有 Vary 时按逗号段合并 Origin，不覆盖既有值。 */
    private static function varyOn(Response $response): Response
    {
        $existing = $response->getHeader('Vary');
        if (is_array($existing)) {
            $existing = implode(',', array_filter($existing, 'is_string'));
        }
        if (is_string($existing) && $existing !== '') {
            $tokens = array_map('trim', explode(',', $existing));
            $found = false;
            foreach ($tokens as $token) {
                if (strcasecmp($token, 'Origin') === 0) {
                    $found = true;
                    break;
                }
            }

            return $found ? $response : $response->withHeader('Vary', $existing . ', Origin');
        }

        return $response->withHeader('Vary', 'Origin');
    }
}
