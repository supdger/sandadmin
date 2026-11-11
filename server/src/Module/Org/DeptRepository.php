<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Sandadmin\Infra\Db\Repository;
use Sandadmin\Infra\Db\Row;

/**
 * sand_system_dept（部门树）仓储。硬删除表，非空保护由服务层预检 + 外键 RESTRICT 兜住。
 *
 * @extends Repository<Dept>
 */
final class DeptRepository extends Repository
{
    protected function table(): string
    {
        return 'sand_system_dept';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'parent_id', 'name', 'leader_id', 'phone', 'sort', 'status', 'create_time', 'update_time'];
    }

    /** @return array<string, string> */
    protected function fieldMap(): array
    {
        return [
            'id' => 'id',
            'parent_id' => 'parent_id',
            'name' => 'name',
            'leader_id' => 'leader_id',
            'sort' => 'sort',
            'status' => 'status',
            'create_time' => 'create_time',
        ];
    }

    protected function hydrate(Row $row): Dept
    {
        return new Dept(
            $row->int('id'),
            $row->isNull('parent_id') ? null : $row->int('parent_id'),
            $row->string('name'),
            $row->isNull('leader_id') ? null : $row->int('leader_id'),
            $row->isNull('phone') ? null : $row->string('phone'),
            $row->int('sort'),
            $row->int('status'),
            $row->dateTime('create_time'),
            $row->dateTime('update_time'),
        );
    }

    public function find(int $id): ?Dept
    {
        return $this->findById($id);
    }

    /**
     * 锁定并取部门行（SELECT ... FOR UPDATE）；不存在返回 null。
     * 删除路径先锁再复检，避免同事务复检期被并发写入放大判定口径。
     */
    public function lockForUpdate(int $deptId): ?Dept
    {
        $queryBuilder = $this->queryBuilder()->select(...$this->columns())->from($this->table());
        $row = $queryBuilder->where('id = :id')->setParameter('id', $deptId)
            ->forUpdate()->setMaxResults(1)
            ->executeQuery()->fetchAssociative();

        return $row === false ? null : $this->hydrate(new Row($row));
    }

    public function countChildren(int $deptId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sand_system_dept WHERE parent_id = ?',
            [$deptId],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    public function delete(int $deptId): bool
    {
        return $this->deleteRow($deptId) > 0;
    }

    /**
     * 清掉用户担任的部门负责人；返回被置空的部门数。
     * 软删用户时由 UserDeletion 在同一事务调用（FK 只覆盖硬删，软删不会触发 SET NULL）。
     */
    public function clearLeader(int $userId): int
    {
        return (int) $this->connection->update($this->table(), ['leader_id' => null], ['leader_id' => $userId]);
    }

    /**
     * 全量部门的负责人展示视图：LEFT JOIN 用户表，leader_id 为空或用户已软删
     * 时展示字段全 null（按「未设置」），负责人停用（status=2）时 leader_status 为 disabled。
     *
     * @return list<DeptLeader>
     */
    public function listLeaderViews(): array
    {
        $rows = $this->connection->iterateAssociative(<<<'SQL'
            SELECT d.id AS dept_id,
                   CASE WHEN u.id IS NULL OR u.delete_time IS NOT NULL THEN NULL ELSE u.id END AS leader_id,
                   CASE WHEN u.id IS NULL OR u.delete_time IS NOT NULL THEN NULL ELSE u.nickname END AS leader_name,
                   CASE WHEN u.id IS NULL OR u.delete_time IS NOT NULL THEN NULL ELSE u.avatar_file_id END AS leader_avatar_file_id,
                   CASE WHEN u.id IS NULL OR u.delete_time IS NOT NULL THEN NULL
                        WHEN u.status = 2 THEN 'disabled' ELSE 'enabled' END AS leader_status
              FROM sand_system_dept d
              LEFT JOIN sand_system_user u ON u.id = d.leader_id
             ORDER BY d.parent_id NULLS FIRST, d.sort, d.id
        SQL);

        $views = [];
        foreach ($rows as $row) {
            $line = new Row($row);
            $status = $line->isNull('leader_status') ? null : $line->string('leader_status');
            $views[] = new DeptLeader(
                $line->int('dept_id'),
                $line->isNull('leader_id') ? null : $line->int('leader_id'),
                $line->isNull('leader_name') ? null : $line->string('leader_name'),
                $line->isNull('leader_avatar_file_id') ? null : $line->int('leader_avatar_file_id'),
                $status === 'enabled' || $status === 'disabled' ? $status : null,
            );
        }

        return $views;
    }
}
