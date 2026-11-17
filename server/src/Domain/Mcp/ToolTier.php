<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * MCP 工具三档（x-mcp 扩展字段取值，U-3/M-02）。
 * Read 直执；Write 必带幂等键、支持 dry_run；HighRisk 只生成待确认请求。
 */
enum ToolTier: string
{
    case Read = 'read';
    case Write = 'write';
    case HighRisk = 'high_risk';
}
