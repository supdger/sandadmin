<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 工具执行结果。失败时 errorCode 用大写蛇形错误码，payload 里放
 * 给调用方看的结构化信息（不含堆栈、内部路径、密钥）。
 */
final readonly class ToolOutcome
{
    private function __construct(
        public bool $succeeded,
        public mixed $payload,
        public string $errorCode,
    ) {
    }

    public static function ok(mixed $payload): self
    {
        return new self(true, $payload, '');
    }

    public static function fail(string $errorCode, mixed $payload): self
    {
        if ($errorCode === '') {
            throw new \InvalidArgumentException('失败结果必须带错误码');
        }

        return new self(false, $payload, $errorCode);
    }
}
