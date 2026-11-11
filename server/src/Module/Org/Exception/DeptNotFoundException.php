<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org\Exception;

/** 部门不存在或已删除。由上层错误处理映射为 404 DEPT_NOT_FOUND。 */
final class DeptNotFoundException extends \DomainException
{
    public const CODE = 'DEPT_NOT_FOUND';
}
