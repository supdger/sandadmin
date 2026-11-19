<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 单个 MCP 工具的执行契约。参数已经过 SDK 的 inputSchema 校验；
 * 实现不得读取请求态全局状态，身份与请求上下文只从参数拿。
 * context 缺省只允许出现在绕过内核的直接调用里；经 McpKernel
 * 的调用恒有上下文（request_id 与审计同一条）。
 */
interface ToolRunner
{
    /** @param array<string, mixed> $arguments */
    public function run(McpIdentity $identity, array $arguments, ?CallContext $context = null): ToolOutcome;
}
