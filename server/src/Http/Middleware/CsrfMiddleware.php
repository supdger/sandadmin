<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Application\Error\ErrorBody;
use Sandadmin\Application\Security\CsrfConfig;
use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Http\JsonErrorResponse;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestHeaders;
use Sandadmin\Http\RequestIds;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * v1 组写操作的 Origin/自定义头校验（A-5 + 缺口检查第 14 条）。
 * main 上 v1 路由由契约生成表平铺注册、无 group/->middleware()，
 * 因此全局挂载 + 本中间件内按 /api/admin/v1 前缀自限作用域（待架构复审）。
 * 本站源只认部署配置 security.site_origin，禁止读 Host/转发头推导。
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const V1_PREFIX = '/api/admin/v1';

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private readonly CsrfConfig $config;

    private readonly JsonErrorResponse $json;

    private readonly RequestContextStore $contexts;

    private readonly RequestIds $requestIds;

    private readonly RequestHeaders $headers;

    public function __construct(?CsrfConfig $config = null)
    {
        $this->config = $config ?? CsrfConfig::fromConfig();
        $this->json = new JsonErrorResponse();
        $this->contexts = new RequestContextStore();
        $this->requestIds = new RequestIds();
        $this->headers = new RequestHeaders();
    }

    public function process(Request $request, callable $handler): Response
    {
        if (self::appliesTo($request)) {
            if (!$this->originAllowed($request)) {
                return $this->reject($request, '请求来源与站点配置不一致');
            }
            if (!$this->hasMarker($request)) {
                return $this->reject($request, '请求缺少安全校验头 ' . $this->config->requiredHeaderName);
            }
        }
        $response = ($handler)($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('CSRF 中间件下游必须返回 Response');
        }

        return $response;
    }

    private static function appliesTo(Request $request): bool
    {
        if (!str_starts_with($request->path(), self::V1_PREFIX)) {
            return false;
        }

        return !in_array(strtoupper($request->method()), self::SAFE_METHODS, true);
    }

    /**
     * Origin 三态：存在即与 site_origin 精确相等（'null' 字面量必然不等→拒）；
     * 缺失才回落 Referer 同源解析；两者皆无 → 拒。
     * site_origin 未配置时跳过本源校验（标记头仍必查）。
     */
    private function originAllowed(Request $request): bool
    {
        $siteOrigin = $this->config->siteOrigin;
        if ($siteOrigin === null) {
            return true;
        }
        $origin = $this->headers->optional($request, 'origin');
        if ($origin !== null) {
            return CsrfConfig::normalizeOrigin($origin) === $siteOrigin;
        }
        $referer = $this->headers->optional($request, 'referer');
        if ($referer === null) {
            return false;
        }

        return self::parseOrigin($referer) === $siteOrigin;
    }

    /** 从 Referer 还原 scheme://host[:port]；解析不出即不信任。 */
    private static function parseOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
            return null;
        }
        $origin = strtolower($scheme . '://' . $host);
        $port = $parts['port'] ?? null;
        if (is_int($port)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    private function hasMarker(Request $request): bool
    {
        $value = $this->headers->optional($request, $this->config->requiredHeaderName);
        if ($value === null) {
            return false;
        }
        $expected = $this->config->requiredHeaderValue;

        return $expected === null || $value === $expected;
    }

    private function reject(Request $request, string $message): Response
    {
        return $this->json->fromBody(
            new ErrorBody(403, 'CSRF_REJECTED', $message, [], $this->ensureRequestId($request)),
        );
    }

    private function ensureRequestId(Request $request): string
    {
        $existing = $this->contexts->get($request);
        if ($existing !== null) {
            return $existing->requestId;
        }
        $requestId = $this->requestIds->resolve($this->headers->optional($request, 'x-request-id'));
        $this->contexts->set($request, new RequestContext(null, $requestId));

        return $requestId;
    }
}
