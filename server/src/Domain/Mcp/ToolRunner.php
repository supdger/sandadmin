<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 单个 MCP 工具的执行契约。参数已经过 SDK 的 inputSchema 校验；
 * 实现不得读取请求态全局状态，身份只从参数拿。
 */
interface ToolRunner
{
    /** @param array<string, mixed> $arguments */
    public function run(McpIdentity $identity, array $arguments): ToolOutcome;
}
