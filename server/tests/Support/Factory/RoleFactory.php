<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support\Factory;

use Sandadmin\Module\Org\Role;

/** Unit 用内存工厂：不连库造 sand_system_role 行值对象。 */
final class RoleFactory
{
    private const FIXED_TIME = '2026-01-01T00:00:00+00:00';

    public static function make(
        int $id = 1,
        string $name = '测试角色',
        string $code = 'test_role',
        ?string $remark = null,
        string $dataScope = 'self',
        bool $isSystem = false,
        bool $isSuper = false,
        int $status = 1,
        int $authzVersion = 1,
        ?\DateTimeImmutable $createTime = null,
        ?\DateTimeImmutable $updateTime = null,
    ): Role {
        $fixed = new \DateTimeImmutable(self::FIXED_TIME);
        return new Role(
            $id,
            $name,
            $code,
            $remark,
            $dataScope,
            $isSystem,
            $isSuper,
            $status,
            $authzVersion,
            $createTime ?? $fixed,
            $updateTime ?? $fixed,
        );
    }
}
