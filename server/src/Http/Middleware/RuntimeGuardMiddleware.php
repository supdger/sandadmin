<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\Middleware;

use Sandadmin\Http\RequestContextStore;
use Sandadmin\Infra\Runtime\ProcessRecycle;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 请求生命周期守卫（3i P2 / S10）：全局最外层中间件。
 * 收尾时先清掉生命周期自有的 request_context 键，再断言上下文存储
 * 无任何残留——有代码往里写了东西却不清理会抛 ContextResidueException，
 * 请求按 500 失败而不是把脏状态带给下一个请求。同时给进程回收计数
 * 喂数，达到 stream.profiles.web.max_requests 触发 worker 平滑重启。
 */
final class RuntimeGuardMiddleware implements MiddlewareInterface
{
    private readonly RequestContextStore $contexts;

    private readonly ProcessRecycle $recycle;

    public function __construct(?RequestContextStore $contexts = null, ?ProcessRecycle $recycle = null)
    {
        $this->contexts = $contexts ?? new RequestContextStore();
        $this->recycle = $recycle ?? ProcessRecycle::fromConfig();
    }

    public function process(Request $request, callable $handler): Response
    {
        try {
            $response = ($handler)($request);
        } finally {
            $this->contexts->clear();
            $this->contexts->assertResidueFree();
            $this->recycle->noteRequest();
        }
        if (!$response instanceof Response) {
            throw new \RuntimeException('运行时守卫中间件下游必须返回 Response');
        }

        return $response;
    }
}
