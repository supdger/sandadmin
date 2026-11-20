<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration\Exception;

/**
 * 迁移锁被另一执行者持有。不排队等待，直接拒绝。
 */
final class MigrationBusyException extends MigrationException
{
}
