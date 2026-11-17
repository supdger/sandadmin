<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * MCP 调用审计端口。M-08 规划的安全日志统一入口（AuditSinkInterface）
 * 落地前，MCP 先有自己的窄接口：每次调用记一条 McpCall。
 * 换实现（库表 / 列式库）时业务代码不动。
 */
interface AuditSink
{
    /** 只许成功记账不许影响调用结果：实现内部吞掉自身故障。 */
    public function record(McpCall $call): void;
}
