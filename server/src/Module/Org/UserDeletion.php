<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\Connection;

/**
 * 删除用户（软删除）及其表级联动：同一事务内置 delete_time、清 dept_id、
 * 清掉其担任的部门负责人。手机号/登录账号由部分唯一索引在删除后自动释放，
 * 不做置空改写。
 */
final readonly class UserDeletion
{
    public function __construct(
        private Connection $connection,
        private UserRepository $users,
        private DeptRepository $depts,
    ) {
    }

    /**
     * 幂等删除：用户不存在或已删除返回 false，不抛错。
     * 停用/删除前的待处理事项检查（disable-check、转交回调）属上层业务，不在本层。
     */
    public function delete(int $userId): bool
    {
        return $this->connection->transactional(function () use ($userId): bool {
            if ($this->users->markDeleted($userId) === 0) {
                return false;
            }
            $this->depts->clearLeader($userId);

            return true;
        });
    }
}
