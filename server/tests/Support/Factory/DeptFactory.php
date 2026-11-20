<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support\Factory;

use Sandadmin\Module\Org\Dept;

/** Unit 用内存工厂：不连库造 sand_system_dept 行值对象。 */
final class DeptFactory
{
    private const FIXED_TIME = '2026-01-01T00:00:00+00:00';

    public static function make(
        int $id = 1,
        ?int $parentId = null,
        string $name = '测试部门',
        ?int $leaderId = null,
        ?string $phone = null,
        int $sort = 0,
        int $status = 1,
        ?\DateTimeImmutable $createTime = null,
        ?\DateTimeImmutable $updateTime = null,
    ): Dept {
        $fixed = new \DateTimeImmutable(self::FIXED_TIME);
        return new Dept(
            $id,
            $parentId,
            $name,
            $leaderId,
            $phone,
            $sort,
            $status,
            $createTime ?? $fixed,
            $updateTime ?? $fixed,
        );
    }
}
