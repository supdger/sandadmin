<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration\Exception;

/**
 * 单份迁移执行失败：该迁移已整体回滚，账本已记 failed，后续执行一律拒绝。
 */
final class MigrationFailedException extends MigrationException
{
}
