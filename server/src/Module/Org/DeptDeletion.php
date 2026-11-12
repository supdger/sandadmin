<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\Exception\DeptNotEmptyException;
use Sandadmin\Module\Org\Exception\DeptNotFoundException;

/**
 * 删除部门（硬删除）及其表级联动：复检非空、删部门行、把数据范围指定集合
 * 因此变空的角色改为 self，全部在同一事务。role_dept 行由外键 CASCADE 清掉。
 */
final readonly class DeptDeletion
{
    public function __construct(
        private Connection $connection,
        private DeptRepository $depts,
        private UserRepository $users,
        private RoleRepository $roles,
    ) {
    }

    /**
     * 删除预检（GET /depts/{dept_id}/delete-check 的数据源）。
     * 口径与 delete() 事务内复检一致，但本方法不加锁、可能有读删竞态，
     * 最终判定以 delete() 的锁内复检为准。
     */
    public function check(int $deptId): DeptDeleteCheck
    {
        if ($this->depts->find($deptId) === null) {
            throw new DeptNotFoundException('部门不存在或已被删除');
        }

        return $this->buildCheck($deptId);
    }

    /**
     * 删除部门。部门不存在抛 DeptNotFoundException；仍有未删除用户或下级
     * 部门抛 DeptNotEmptyException，整事务回滚。
     */
    public function delete(int $deptId): void
    {
        $this->connection->transactional(function () use ($deptId): void {
            if ($this->depts->lockForUpdate($deptId) === null) {
                throw new DeptNotFoundException('部门不存在或已被删除');
            }
            $check = $this->buildCheck($deptId);
            if ($check->childCount > 0 && $check->userCount > 0) {
                throw new DeptNotEmptyException('里面还有人。先删掉或移走下级部门，再把人调走');
            }
            if ($check->childCount > 0) {
                throw new DeptNotEmptyException('先删掉或移走下级部门');
            }
            if ($check->userCount > 0) {
                throw new DeptNotEmptyException('里面还有人。先把人调到别的部门，再来删除');
            }
            $this->depts->delete($deptId);
            $this->roles->scopeToSelf(array_column($check->affectedRoles, 'id'));
        });
    }

    private function buildCheck(int $deptId): DeptDeleteCheck
    {
        return new DeptDeleteCheck(
            $this->users->countInDept($deptId),
            $this->depts->countChildren($deptId),
            $this->roles->listSolelyScopedTo($deptId),
        );
    }
}
