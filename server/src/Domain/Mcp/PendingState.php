<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 高危写「待确认请求」状态机（U-3/M-02）：待确认 → 已批准 / 已拒绝 / 已过期。
 * 本期只立形状；高危工具一律不直接执行。
 */
enum PendingState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
