<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Domain\Auth\Authenticator;
use Sandadmin\Domain\Auth\RequestContext;
use Sandadmin\Domain\Auth\SessionCookie;
use Sandadmin\Domain\Exception\UnauthenticatedException;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestHeaders;
use Sandadmin\Http\RequestIds;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 校验 sandadmin_session 会话 Cookie 并写入 RequestContext。
 * 不保存用户、令牌或 request id 到实例属性；公开路由不挂本中间件。
 * 成功响应补 X-Authz-Version，供前端感知权限版本变化（api-conventions §4/§8）。
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly RequestContextStore $contexts,
        private readonly RequestIds $requestIds,
        private readonly RequestHeaders $headers,
    ) {
    }

    public function process(Request $request, callable $handler): Response
    {
        $requestId = $this->requestId($request);
        $this->contexts->set($request, new RequestContext(null, $requestId));

        $user = $this->authenticator->authenticate($this->credential($request));
        $this->contexts->set($request, new RequestContext($user, $requestId));

        $response = $handler($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('鉴权中间件下游必须返回 Response');
        }

        return $response->withHeader('X-Authz-Version', (string) $user->authzVersion);
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
        $token = $request->cookie(SessionCookie::NAME);
        if (!is_string($token)) {
            throw new UnauthenticatedException();
        }
        $token = trim($token);
        if ($token === '') {
            throw new UnauthenticatedException();
        }

        return $token;
    }
}
