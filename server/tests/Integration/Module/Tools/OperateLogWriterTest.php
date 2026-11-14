<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Sandadmin\Module\AuditLog\OperateLogWriter;
use Sandadmin\Module\Org\OrgSql;

/**
 * user.operateLog 的写库契约：插件 operateLog 监听器写的旧表
 * sand_system_oper_log 在 1.x 基线不存在（本测试顺带留证），
 * 宿主 OperateLogWriter 是唯一写入方，落库行必须满足
 * sand_system_audit_log 的 CHECK/NOT NULL 与 actor_id 外键。
 */
final class OperateLogWriterTest extends ToolsCase
{
    /** @param array<string, mixed> $overrides */
    private function writeRow(array $overrides = []): void
    {
        OperateLogWriter::write($this->conn, array_merge([
            'actor_type' => 'user',
            'actor_id' => null,
            'actor_name' => 'admin',
            'module' => 'sandadmin',
            'action' => 'usersStore',
            'action_text' => '未命名业务',
            'target_type' => 'request',
            'target_id' => '/api/admin/v1/users',
            'target_label' => 'OrgController::usersStore',
            'succeeded' => true,
            'changes' => '{"name":"示例","password":"******"}',
            'ip' => '127.0.0.1',
            'location' => '内网IP',
            'device_label' => '用工具访问',
            'request_id' => 'qa-oplog-001',
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function lastRow(): array
    {
        // succeeded/changes/create_time 显式 ::text，绕开 PDO 方言差异
        $row = $this->conn->fetchAssociative(
            'SELECT actor_type, actor_id, actor_name, module, action, action_text,'
            . ' target_type, target_id, target_label, succeeded::text AS ok,'
            . ' changes::text AS body, host(ip) AS addr, location, device_label,'
            . ' request_id, create_time::text AS created'
            . ' FROM sand_system_audit_log ORDER BY id DESC LIMIT 1',
        );
        self::assertNotFalse($row);

        return $row;
    }

    public function testRowPersistsContractFields(): void
    {
        $this->writeRow();

        self::assertSame(1, OrgSql::int($this->conn->fetchOne('SELECT COUNT(*) FROM sand_system_audit_log')));
        $row = $this->lastRow();
        self::assertSame('user', $row['actor_type']);
        self::assertNull($row['actor_id']);
        self::assertSame('admin', $row['actor_name']);
        self::assertSame('sandadmin', $row['module']);
        self::assertSame('usersStore', $row['action']);
        self::assertSame('未命名业务', $row['action_text']);
        self::assertSame('request', $row['target_type']);
        self::assertSame('/api/admin/v1/users', $row['target_id']);
        self::assertSame('OrgController::usersStore', $row['target_label']);
        self::assertSame('true', $row['ok']);
        $changes = json_decode(OrgSql::str($row['body']), true);
        self::assertIsArray($changes);
        self::assertSame('示例', $changes['name']);
        self::assertSame('******', $changes['password']);
        self::assertSame('127.0.0.1', $row['addr']);
        self::assertSame('内网IP', $row['location']);
        self::assertSame('用工具访问', $row['device_label']);
        self::assertSame('qa-oplog-001', $row['request_id']);
        // timestamptz(0) 默认 CURRENT_TIMESTAMP：带时区偏移，与其他 writer 一致
        self::assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}/', OrgSql::str($row['created']));
    }

    public function testActorIdHonorsForeignKey(): void
    {
        $actor = $this->userActor();
        $this->writeRow(['actor_id' => $actor->userId]);

        self::assertSame($actor->userId, OrgSql::int($this->lastRow()['actor_id']));
    }

    public function testLegacyOperLogTableIsAbsent(): void
    {
        // 缺陷实证：1.x 基线没有 0.x 插件的 sand_system_oper_log
        self::assertNotTrue((bool) $this->conn->fetchOne("SELECT to_regclass('sand_system_oper_log')"));
        try {
            $this->conn->insert('sand_system_oper_log', [
                'username' => 'admin',
                'method' => 'POST',
                'router' => '/api/admin/v1/x',
                'service_name' => '未命名业务',
                'app' => 'sandadmin',
                'ip' => '127.0.0.1',
                'ip_location' => '内网IP',
                'request_data' => '{}',
            ]);
            self::fail('旧表形状插入应抛 undefined table');
        } catch (\Throwable $error) {
            self::assertMatchesRegularExpression('/oper_log|does not exist|不存在/i', $error->getMessage());
        }
    }

    public function testWriteFailurePropagatesWithoutSecretLeak(): void
    {
        // actor_id 外键违规必然失败；异常对外真实抛出（record() 入口才吞成 error_log），
        // 且错误信息不含请求参数原文（落库前已脱敏为掩码占位符）
        try {
            $this->writeRow(['actor_id' => 999999]);
            self::fail('外键违规应抛出异常');
        } catch (\Throwable $error) {
            self::assertMatchesRegularExpression('/audit_log|actor|violat|constraint/i', $error->getMessage());
            self::assertStringNotContainsString('super-secret', $error->getMessage());
        }
        self::assertSame(0, OrgSql::int($this->conn->fetchOne('SELECT COUNT(*) FROM sand_system_audit_log')));
    }

    public function testInvalidIpStoredAsNull(): void
    {
        $this->writeRow(['ip' => 'not-an-ip']);

        self::assertNull($this->lastRow()['addr']);
    }
}
