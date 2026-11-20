<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

/**
 * 账本 sand_system_schema_migration 的一行。
 * checksum / filename 记的是最后一次操作实际执行的文件
 * （direction=up 记升级文件，direction=down 记回退文件）。
 */
final class LedgerRow
{
    // direction/status 不做字面量收窄：账本是外部状态，非法值要原样交给一致性检查器报告
    public function __construct(
        public readonly string $namespace,
        public readonly string $version,
        public readonly string $filename,
        public readonly string $checksum,
        public readonly string $direction,
        public readonly string $status,
        public readonly ?string $releaseVersion,
        public readonly string $appliedBy,
        public readonly string $appliedTime,
        public readonly int $executionMs,
    ) {
    }
}
