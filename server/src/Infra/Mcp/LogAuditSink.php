<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp;

use Psr\Log\LoggerInterface;
use Sandadmin\Domain\Mcp\AuditSink;
use Sandadmin\Domain\Mcp\McpCall;

/**
 * MCP 审计落 PSR-3 日志通道。McpCall 本身只携带脱敏字段
 * （参数摘要而非原文、凭据标识而非令牌）；记账失败吞掉，
 * 按 AuditSink 契约不得影响工具调用结果。
 */
final class LogAuditSink implements AuditSink
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function record(McpCall $call): void
    {
        try {
            $this->logger->info('mcp 工具调用', $call->toLogContext());
        } catch (\Throwable) {
            // 审计通道故障不反噬调用链；故障本身由日志通道上层观测
        }
    }
}
