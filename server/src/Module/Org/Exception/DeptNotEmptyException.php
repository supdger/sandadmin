<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org\Exception;

/** 部门下还有未删除用户或下级部门，删除被拒绝。由上层错误处理映射为 409 DEPT_NOT_EMPTY。 */
final class DeptNotEmptyException extends \DomainException
{
    public const CODE = 'DEPT_NOT_EMPTY';
}
