<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Sandadmin\Infra\Db\Repository;
use Sandadmin\Infra\Db\Row;

/**
 * sand_system_user（后台管理账号）仓储。软删除表；
 * 口令与令牌相关列不进入值对象，由认证层单独处理。
 *
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    protected function table(): string
    {
        return 'sand_system_user';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return [
            'id', 'username', 'phone', 'nickname', 'email', 'avatar_file_id', 'dept_id',
            'post_id', 'is_super', 'is_admin', 'authz_version', 'status',
            'last_login_time', 'create_time', 'update_time', 'delete_time',
        ];
    }

    /** @return array<string, string> */
    protected function fieldMap(): array
    {
        return [
            'id' => 'id',
            'username' => 'username',
            'phone' => 'phone',
            'nickname' => 'nickname',
            'dept_id' => 'dept_id',
            'status' => 'status',
            'create_time' => 'create_time',
            'last_login_time' => 'last_login_time',
        ];
    }

    protected function hydrate(Row $row): User
    {
        return new User(
            id: $row->int('id'),
            username: $row->isNull('username') ? null : $row->string('username'),
            phone: $row->string('phone'),
            nickname: $row->string('nickname'),
            email: $row->isNull('email') ? null : $row->string('email'),
            avatarFileId: $row->isNull('avatar_file_id') ? null : $row->int('avatar_file_id'),
            deptId: $row->isNull('dept_id') ? null : $row->int('dept_id'),
            postId: $row->isNull('post_id') ? null : $row->int('post_id'),
            isSuper: $row->bool('is_super'),
            isAdmin: $row->bool('is_admin'),
            authzVersion: $row->int('authz_version'),
            status: $row->int('status'),
            lastLoginTime: $row->isNull('last_login_time') ? null : $row->dateTime('last_login_time'),
            createTime: $row->dateTime('create_time'),
            updateTime: $row->dateTime('update_time'),
            deleteTime: $row->isNull('delete_time') ? null : $row->dateTime('delete_time'),
        );
    }

    public function find(int $id): ?User
    {
        return $this->findById($id);
    }

    /** 部门下未删除用户数（删除部门预检口径）。 */
    public function countInDept(int $deptId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_user WHERE dept_id = ? AND delete_time IS NULL',
            [$deptId],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * 软删除用户：置 delete_time 并把 dept_id 置空（基线 ck_sand_system_user_dept_id 允许）。
     * 手机号与登录账号不动：部分唯一索引只约束 delete_time IS NULL 的行，删除即自动释放。
     * WHERE delete_time IS NULL 使重复删除天然幂等；返回受影响行数（0 表示不存在或已删）。
     */
    public function markDeleted(int $userId): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE sand_system_user'
            . ' SET delete_time = CURRENT_TIMESTAMP, dept_id = NULL, update_time = CURRENT_TIMESTAMP'
            . ' WHERE id = ? AND delete_time IS NULL',
            [$userId],
        );
    }
}
