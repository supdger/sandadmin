<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Http;

use RuntimeException;

/**
 * 出站 HTTP 内部失败：SSRF 拦截、传输错误、超时。
 * 属基础设施异常，不映射 openapi 错误码，由调用方决定如何呈现。
 */
final class OutboundException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $kind = 'transport',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** 失败类别：ssrf / transport / timeout，供调用方分支与日志归类。 */
    public function kind(): string
    {
        return $this->kind;
    }
}
