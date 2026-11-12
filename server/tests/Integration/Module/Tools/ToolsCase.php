<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;

/**
 * 监控与工具（M1/M2/J10/J11/设置/搜索）集成测试基类。
 * 每个用例前清空相关表；需要 SAND_TEST_DB_URL 指向已应用 0001-0007 的库。
 */
abstract class ToolsCase extends TestCase
{
    protected Connection $conn;
    protected OrgAudit $audit;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        $this->conn = DriverManager::getConnection((new DsnParser())->parse($url));
        $this->resetTables();
        $this->audit = new OrgAudit($this->conn);
    }

    protected function tearDown(): void
    {
        if (isset($this->conn)) {
            $this->conn->close();
        }
    }

    private function resetTables(): void
    {
        $this->conn->executeStatement('DELETE FROM sand_system_login_log');
        $this->conn->executeStatement('DELETE FROM sand_system_audit_log');
        $this->conn->executeStatement('DELETE FROM sand_system_schedule_run');
        $this->conn->executeStatement('DELETE FROM sand_system_schedule');
        $this->conn->executeStatement('DELETE FROM sand_system_metric_sample');
        $this->conn->executeStatement('DELETE FROM sand_system_task_run');
        $this->conn->executeStatement('DELETE FROM sand_system_setting');
        $this->conn->executeStatement('DELETE FROM sand_system_file');
        $this->conn->executeStatement('DELETE FROM sand_system_plugin');
        // 先于用户表清：requested_by 有外键
        $this->conn->executeStatement('DELETE FROM sand_system_lifecycle_request');
        $this->conn->executeStatement('DELETE FROM sand_system_user');
        $this->conn->executeStatement('DELETE FROM sand_system_dept');
    }

    /** @param list<string> $permissions */
    protected function actor(array $permissions = []): OrgActor
    {
        return new OrgActor(0, '测试管理员', null, true, $permissions);
    }

    /** @param list<string> $permissions 建一行真实用户当操作人：task_run.created_by、audit_log.actor_id 有外键。 */
    protected function userActor(array $permissions = []): OrgActor
    {
        $this->conn->insert('sand_system_dept', ['name' => '测试部门', 'sort' => 1]);
        $deptId = (int) $this->conn->lastInsertId();
        $this->conn->insert('sand_system_user', [
            'phone' => '13800000001',
            'nickname' => '测试管理员',
            'password_hash' => 'argon2id$test',
            'dept_id' => $deptId,
            'is_super' => 'true',
        ]);

        return new OrgActor((int) $this->conn->lastInsertId(), '测试管理员', $deptId, true, $permissions);
    }
}
