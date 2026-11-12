<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/**
 * 公告模块当前操作人。is_super 用户拥有全部权限；
 * deptId 用于 dept 范围公告的收件人可见性判定。
 */
final readonly class AnnouncementActor
{
    /** @param list<string> $permissions */
    public function __construct(
        public int $userId,
        public string $name,
        public ?int $deptId,
        public array $permissions,
        public bool $isSuper = false,
    ) {
    }

    public function can(string $permission): bool
    {
        return $this->isSuper || in_array($permission, $this->permissions, true);
    }

    /** @param list<string> $permissions 任一持有即放行 */
    public function canAny(array $permissions): bool
    {
        if ($this->isSuper) {
            return true;
        }
        foreach ($permissions as $permission) {
            if (in_array($permission, $this->permissions, true)) {
                return true;
            }
        }

        return false;
    }
}
