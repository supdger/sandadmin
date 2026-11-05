<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Domain\Auth\Authenticator;
use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Domain\Exception\UnauthenticatedException;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestHeaders;
use Sandadmin\Http\RequestIds;
use Sandadmin\Http\Timezones;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 认证当前请求并写入 RequestContext。
 * 不保存用户、凭证或 request id 到实例属性。
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly RequestContextStore $contexts,
        private readonly RequestIds $requestIds,
        private readonly Timezones $timezones,
        private readonly RequestHeaders $headers,
    ) {
    }

    public function process(Request $request, callable $handler): Response
    {
        $requestId = $this->requestId($request);
        $this->contexts->set($request, new RequestContext(null, 0, $requestId, new \DateTimeZone(Timezones::DEFAULT)));

        $user = $this->authenticator->authenticate($this->credential($request));
        $timezone = $this->timezones->resolve($this->headers->optional($request, 'x-timezone'), $user->timezone);
        $this->contexts->set($request, new RequestContext(
            $user,
            $user->permissionVersion,
            $requestId,
            $timezone,
        ));

        $response = $handler($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('鉴权中间件下游必须返回 Response');
        }

        return $response;
    }

    private function requestId(Request $request): string
    {
        $existing = $this->contexts->get($request);
        if ($existing !== null) {
            return $existing->requestId;
        }

        return $this->requestIds->resolve($this->headers->optional($request, 'x-request-id'));
    }

    private function credential(Request $request): string
    {
        $header = $this->headers->optional($request, 'authorization');
        $prefix = 'Bearer ';
        if ($header === null || !str_starts_with($header, $prefix)) {
            throw new UnauthenticatedException();
        }
        $token = substr($header, strlen($prefix));
        if ($token === '' || str_contains($token, ' ')) {
            throw new UnauthenticatedException();
        }

        return $token;
    }
}
