<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 一次工具调用的请求级上下文：request_id 与调用方来源。
 * 由 McpKernel 按调用装配并随 run() 传入；实现不得改从全局态取，
 * 常驻进程下请求态只许走参数。
 */
final readonly class CallContext
{
    public function __construct(
        public string $requestId,
        public ?string $ip,
        public string $device,
    ) {
        if ($this->requestId === '') {
            throw new \InvalidArgumentException('调用上下文的 request_id 不能为空');
        }
    }
}
