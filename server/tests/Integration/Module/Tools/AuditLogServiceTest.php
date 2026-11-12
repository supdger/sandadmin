<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Sandadmin\Module\AuditLog\AuditLogService;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/** J10 操作日志：列表过滤、详情、模块选项、导出落任务与审计。 */
final class AuditLogServiceTest extends ToolsCase
{
    /** @param array<string, mixed> $overrides */
    private function addRow(array $overrides = []): int
    {
        $this->conn->insert('sand_system_audit_log', array_merge([
            'actor_type' => 'user',
            'actor_id' => null,
            'actor_name' => '管理员',
            'module' => 'user',
            'action' => 'update',
            'action_text' => '停用了用户张三',
            'target_type' => 'user',
            'target_id' => '1',
            'target_label' => '张三',
            'succeeded' => 'true',
            'changes' => null,
            'ip' => '127.0.0.1',
            'location' => '内部网络',
            'device_label' => 'Mac 上的 Chrome',
            'request_id' => 'req-audit-1',
        ], $overrides));

        return (int) $this->conn->lastInsertId();
    }

    public function testPageWithFilters(): void
    {
        $this->addRow();
        $this->addRow(['module' => 'setting', 'succeeded' => 'false', 'action_text' => '更新了系统设置', 'target_label' => '基础设置']);
        $service = new AuditLogService($this->conn, $this->audit);

        self::assertSame(2, $service->page([])['total']);
        self::assertSame(1, $service->page(['module' => 'user'])['total']);
        self::assertSame(1, $service->page(['succeeded' => 'false'])['total']);
        self::assertSame(1, $service->page(['keyword' => '张三'])['total']);
        $item = $service->page(['module' => 'setting'])['items'][0];
        self::assertNotNull($item['module_label']);
    }

    public function testDetailIncludesChangesAndIp(): void
    {
        $id = $this->addRow(['changes' => '{"name":{"before":"旧","after":"新"}}']);
        $service = new AuditLogService($this->conn, $this->audit);
        $detail = $service->detail($id);

        self::assertSame('停用了用户张三', $detail['action_text']);
        self::assertSame('127.0.0.1', $detail['ip']);
        self::assertIsArray($detail['changes']);
    }

    public function testDetailMissingThrowsNotFound(): void
    {
        $service = new AuditLogService($this->conn, $this->audit);
        $this->expectException(OrgException::class);
        $service->detail(999999);
    }

    public function testModuleOptionsContainHostModules(): void
    {
        $service = new AuditLogService($this->conn, $this->audit);
        $options = $service->moduleOptions();
        $keys = array_column($options, 'key');

        self::assertContains('user', $keys);
        self::assertContains('setting', $keys);
        self::assertContains('schedule', $keys);
    }

    public function testExportCreatesTaskFileAndAuditRow(): void
    {
        $this->addRow();
        $service = new AuditLogService($this->conn, $this->audit);
        $result = $service->export([], $this->userActor(), 'req-export-a', '127.0.0.1', 'Mac');

        self::assertSame('succeeded', $result['state']);
        $task = $this->conn->fetchAssociative('SELECT type FROM sand_system_task_run WHERE public_id::text = ?', [$result['task_id']]);
        self::assertNotFalse($task);
        self::assertSame('audit-log.export', $task['type']);
        // 导出动作本身也落审计（共 2 条）
        self::assertSame(2, OrgSql::int($this->conn->fetchOne('SELECT COUNT(*) FROM sand_system_audit_log')));
    }
}
