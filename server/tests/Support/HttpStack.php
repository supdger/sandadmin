<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Application\Error\ErrorResponder;
use Sandadmin\Domain\Auth\Authenticator;
use Sandadmin\Http\JsonErrorResponse;
use Sandadmin\Http\Middleware\AuthMiddleware;
use Sandadmin\Http\Middleware\ErrorMiddleware;
use Sandadmin\Http\RequestContextStore;
use Sandadmin\Http\RequestHeaders;
use Sandadmin\Http\RequestIds;

/** 测试用的中间件组装。实例可跨请求复用，以暴露进程级状态泄漏。 */
final class HttpStack
{
    public function __construct(
        public readonly ErrorMiddleware $errors,
        public readonly AuthMiddleware $auth,
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
            $contexts,
            $logger,
        );
    }
}
