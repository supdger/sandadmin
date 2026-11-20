<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Http\RequestContextStore;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 全局请求上下文入口：每个请求进门先经 RequestContextStore::ensure
 * 确定 request_id 并绑定日志作用域，后续中间件与控制器统一复用，
 * 不再各自生成。本中间件不改写响应；X-Request-Id 头由 ErrorMiddleware
 * 与任务接口按契约各自盖章。
 */
final class RequestContextMiddleware implements MiddlewareInterface
{
    private readonly RequestContextStore $contexts;

    public function __construct(?RequestContextStore $contexts = null)
    {
        $this->contexts = $contexts ?? new RequestContextStore();
    }

    public function process(Request $request, callable $handler): Response
    {
        $this->contexts->ensure($request);
        $response = ($handler)($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('请求上下文中间件下游必须返回 Response');
        }

        return $response;
    }
}
