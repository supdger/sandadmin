<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration\Exception;

/**
 * 半成品库：账本校验不通过（failed 记录、checksum 不符、序列断档）。
 * message 末尾附修复指引。
 */
final class MigrationConsistencyException extends MigrationException
{
}
