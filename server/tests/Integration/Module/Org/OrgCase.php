<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Org\DeptRepository;
use Sandadmin\Module\Org\RoleRepository;
use Sandadmin\Module\Org\UserRepository;

/**
 * Org 模块集成测试基类：依赖基线迁移建好的 sand_system_user/dept/role/role_dept。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的专用 PostgreSQL 库；
 * 每个用例前清空四张表再种最小数据，不对库做结构改动。
 */
abstract class OrgCase extends TestCase
{
    protected Connection $conn;
    protected DeptRepository $depts;
    protected UserRepository $users;
    protected RoleRepository $roles;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        $this->conn = DriverManager::getConnection((new DsnParser())->parse($url));
        $this->resetTables();
        $this->depts = new DeptRepository($this->conn);
        $this->users = new UserRepository($this->conn);
        $this->roles = new RoleRepository($this->conn);
    }

    protected function tearDown(): void
    {
        // setUp 因缺 SAND_TEST_DB_URL skip 时 conn 未初始化，tearDown 仍会执行
        if (isset($this->conn)) {
            $this->conn->close();
        }
    }

    /**
     * 清表顺序按外键方向。任务和生命周期的创建人是 RESTRICT，
     * 必须先于用户删除；部门自引用也是 RESTRICT，单语句全删可过。
     */
    private function resetTables(): void
    {
        $this->conn->executeStatement('DELETE FROM sand_system_lifecycle_request');
        $this->conn->executeStatement('DELETE FROM sand_system_task_run');
        $this->conn->executeStatement('DELETE FROM sand_system_role_dept');
        $this->conn->executeStatement('DELETE FROM sand_system_user_role');
        $this->conn->executeStatement('DELETE FROM sand_system_user');
        $this->conn->executeStatement('DELETE FROM sand_system_dept');
        $this->conn->executeStatement('DELETE FROM sand_system_role');
    }

    protected function addDept(string $name, ?int $parentId = null, ?int $leaderId = null): int
    {
        $this->conn->insert('sand_system_dept', [
            'name' => $name,
            'parent_id' => $parentId,
            'leader_id' => $leaderId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    protected function addUser(int $deptId, string $phone, string $nickname = '测试用户', int $status = 1): int
    {
        $this->conn->insert('sand_system_user', [
            'phone' => $phone,
            'nickname' => $nickname,
            'password_hash' => 'argon2id$test',
            'dept_id' => $deptId,
            'status' => $status,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    protected function addRole(string $name, string $dataScope): int
    {
        $this->conn->insert('sand_system_role', [
            'name' => $name,
            'code' => 'code_' . $name,
            'data_scope' => $dataScope,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    protected function scopeRoleTo(int $roleId, int $deptId): void
    {
        $this->conn->insert('sand_system_role_dept', ['role_id' => $roleId, 'dept_id' => $deptId]);
    }

    /** @return array<string, mixed>|null */
    protected function fetchRow(string $table, int $id): ?array
    {
        $row = $this->conn->fetchAssociative(
            "SELECT * FROM {$table} WHERE id = ?",
            [$id],
        );

        return $row === false ? null : $row;
    }

    /** @return list<int> */
    protected function roleDeptIds(int $roleId): array
    {
        $ids = [];
        foreach ($this->conn->iterateColumn(
            'SELECT dept_id FROM sand_system_role_dept WHERE role_id = ? ORDER BY dept_id',
            [$roleId],
        ) as $deptId) {
            if (is_numeric($deptId)) {
                $ids[] = (int) $deptId;
            }
        }

        return $ids;
    }
}
