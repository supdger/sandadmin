<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use ArrayObject;
use Sandadmin\Application\Error\ErrorResponder;
use Sandadmin\Domain\Auth\Authenticator;
use Sandadmin\Http\JsonErrorResponse;
use Sandadmin\Http\Middleware\AuthMiddleware;
use Sandadmin\Http\Middleware\ErrorMiddleware;
use Sandadmin\Http\Middleware\RuntimeGuardMiddleware;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestHeaders;
use Sandadmin\Http\RequestIds;
use Sandadmin\Infra\Runtime\ProcessRecycle;
use Webman\Context;
use Webman\Http\Request;
use Webman\Http\Response;

/** 测试用的中间件组装。实例可跨请求复用，以暴露进程级状态泄漏。 */
final class HttpStack
{
    public function __construct(
        public readonly ErrorMiddleware $errors,
        public readonly AuthMiddleware $auth,
        public readonly RuntimeGuardMiddleware $guard,
        public readonly RequestContextStore $contexts,
        public readonly RecordingErrorLogger $logger,
    ) {
    }

    public static function create(Authenticator $authenticator): self
    {
        $logger = new RecordingErrorLogger();
        $contexts = new RequestContextStore();
        $requestIds = new RequestIds();
        $headers = new RequestHeaders();

        return new self(
            new ErrorMiddleware(new ErrorResponder($logger), new JsonErrorResponse(), $contexts, $requestIds, $headers),
            new AuthMiddleware($authenticator, $contexts, $requestIds, $headers),
            // 测试里回收阈值置 0：不触发 worker 信号，只保留残留断言
            new RuntimeGuardMiddleware($contexts, new ProcessRecycle(0, 0)),
            $contexts,
            $logger,
        );
    }

    /**
     * 模拟 webman 请求生命周期：进入时 Context::reset（等价 App::onMessage），
     * 收尾由 RuntimeGuardMiddleware 断言残留，最后 Context::destroy。
     *
     * @param callable(Request): Response $pipeline 业务中间件链
     */
    public function dispatch(Request $request, callable $pipeline): Response
    {
        Context::reset(new ArrayObject([Request::class => $request], ArrayObject::ARRAY_AS_PROPS));
        try {
            return $this->guard->process($request, static fn (Request $current): Response => $pipeline($current));
        } finally {
            Context::destroy();
        }
    }
}
