<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

use Doctrine\DBAL\ArrayParameterType;
use Sandadmin\Infra\Db\Repository;
use Sandadmin\Infra\Db\Row;

/**
 * sand_system_role（角色）仓储。「指定部门」数据范围的部门清单在
 * sand_system_role_dept，部门删除时由外键 CASCADE 清掉。
 *
 * @extends Repository<Role>
 */
final class RoleRepository extends Repository
{
    protected function table(): string
    {
        return 'sand_system_role';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return [
            'id', 'name', 'code', 'remark', 'data_scope', 'is_system', 'is_super',
            'status', 'authz_version', 'create_time', 'update_time',
        ];
    }

    /** @return array<string, string> */
    protected function fieldMap(): array
    {
        return [
            'id' => 'id',
            'name' => 'name',
            'code' => 'code',
            'data_scope' => 'data_scope',
            'status' => 'status',
            'create_time' => 'create_time',
        ];
    }

    protected function hydrate(Row $row): Role
    {
        return new Role(
            $row->int('id'),
            $row->string('name'),
            $row->string('code'),
            $row->isNull('remark') ? null : $row->string('remark'),
            $row->string('data_scope'),
            $row->bool('is_system'),
            $row->bool('is_super'),
            $row->int('status'),
            $row->int('authz_version'),
            $row->dateTime('create_time'),
            $row->dateTime('update_time'),
        );
    }

    public function find(int $id): ?Role
    {
        return $this->findById($id);
    }

    /**
     * 指定集合恰好只含本部门的「指定部门」（custom）角色：指定集删了本部门就变空，
     * 删除部门后要跟着改 data_scope='self'；指定集还有其他部门的角色不在列。
     *
     * @return list<array{id: int, name: string}>
     */
    public function listSolelyScopedTo(int $deptId): array
    {
        $rows = $this->connection->iterateAssociative(<<<'SQL'
            SELECT r.id, r.name
              FROM sand_system_role r
             WHERE r.data_scope = 'custom'
               AND EXISTS (SELECT 1 FROM sand_system_role_dept d
                            WHERE d.role_id = r.id AND d.dept_id = :dept)
               AND NOT EXISTS (SELECT 1 FROM sand_system_role_dept d
                                WHERE d.role_id = r.id AND d.dept_id <> :dept)
             ORDER BY r.id
        SQL, ['dept' => $deptId]);

        $roles = [];
        foreach ($rows as $row) {
            $line = new Row($row);
            $roles[] = ['id' => $line->int('id'), 'name' => $line->string('name')];
        }

        return $roles;
    }

    /**
     * 把给定角色的数据范围改成 self 并自增 authz_version 使权限缓存失效。
     * 空列表不动；返回受影响行数。
     *
     * @param list<int> $roleIds
     */
    public function scopeToSelf(array $roleIds): int
    {
        if ($roleIds === []) {
            return 0;
        }

        return (int) $this->connection->executeStatement(
            "UPDATE sand_system_role"
            . " SET data_scope = 'self', authz_version = authz_version + 1, update_time = CURRENT_TIMESTAMP"
            . ' WHERE id IN (:ids)',
            ['ids' => $roleIds],
            ['ids' => ArrayParameterType::INTEGER],
        );
    }
}
