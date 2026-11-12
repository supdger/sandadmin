<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/** 组织模块当前操作人。is_super 拥有全部权限。 */
final readonly class OrgActor
{
    /** @param list<string> $permissions */
    public function __construct(
        public int $userId,
        public string $name,
        public ?int $deptId,
        public bool $isSuper,
        public array $permissions,
    ) {
    }

    /** 没有真实用户时不写 created_by / updated_by，避免外键指向 0。 */
    public function writerId(): ?int
    {
        return $this->userId > 0 ? $this->userId : null;
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
