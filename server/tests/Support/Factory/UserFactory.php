<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support\Factory;

use Sandadmin\Module\Org\User;

/** Unit 用内存工厂：不连库造 sand_system_user 行值对象。 */
final class UserFactory
{
    private const FIXED_TIME = '2026-01-01T00:00:00+00:00';

    public static function make(
        int $id = 1,
        ?string $username = 'u_test',
        string $phone = '13800000000',
        string $nickname = '测试用户',
        ?string $email = null,
        ?int $avatarFileId = null,
        ?int $deptId = 1,
        ?int $postId = null,
        bool $isSuper = false,
        bool $isAdmin = true,
        int $authzVersion = 1,
        int $status = 1,
        ?\DateTimeImmutable $lastLoginTime = null,
        ?\DateTimeImmutable $createTime = null,
        ?\DateTimeImmutable $updateTime = null,
        ?\DateTimeImmutable $deleteTime = null,
    ): User {
        $fixed = new \DateTimeImmutable(self::FIXED_TIME);
        return new User(
            $id,
            $username,
            $phone,
            $nickname,
            $email,
            $avatarFileId,
            $deptId,
            $postId,
            $isSuper,
            $isAdmin,
            $authzVersion,
            $status,
            $lastLoginTime,
            $createTime ?? $fixed,
            $updateTime ?? $fixed,
            $deleteTime,
        );
    }
}
