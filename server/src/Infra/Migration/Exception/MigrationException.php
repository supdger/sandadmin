<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration\Exception;

/**
 * 迁移执行器的所有受控错误基类。CLI 按异常类型映射退出码。
 */
class MigrationException extends \RuntimeException
{
}
