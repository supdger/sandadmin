<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Sandadmin\Module\LoginLog\LoginLogService;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/** M2 登录日志：列表不带 ip/user_agent，详情和导出带「登录设备地址」。 */
final class LoginLogServiceTest extends ToolsCase
{
    /** @param array<string, mixed> $overrides */
    private function addRow(array $overrides = []): int
    {
        $this->conn->insert('sand_system_login_log', array_merge([
            'user_id' => null,
            'username_input' => 'admin',
            'succeeded' => 'true',
            'reason' => null,
            'ip' => '127.0.0.1',
            'location' => '内部网络',
            'device_label' => 'Mac 上的 Chrome',
            'user_agent' => 'Mozilla/5.0 Chrome/126',
        ], $overrides));

        return (int) $this->conn->lastInsertId();
    }

    public function testPageHidesIpAndAgentFromList(): void
    {
        $this->addRow();
        $service = new LoginLogService($this->conn, $this->audit);
        $page = $service->page([]);

        self::assertSame(1, $page['total']);
        $item = $page['items'][0];
        self::assertArrayNotHasKey('ip', $item);
        self::assertArrayNotHasKey('user_agent', $item);
        // 成功记录 reason_text 是空（不是「-」）
        self::assertNull($item['reason_text']);
    }

    public function testFailedRowHasChineseReasonText(): void
    {
        $this->addRow(['succeeded' => 'false', 'reason' => 'bad_password']);
        $service = new LoginLogService($this->conn, $this->audit);
        $page = $service->page(['succeeded' => 'false']);

        self::assertSame('密码不对', $page['items'][0]['reason_text']);
    }

    public function testDetailIncludesIpAndAgent(): void
    {
        $id = $this->addRow();
        $service = new LoginLogService($this->conn, $this->audit);
        $detail = $service->detail($id);

        // 契约 LoginLogDetail：详情带 ip 与 user_agent（前端用来渲染登录设备地址）
        self::assertSame('127.0.0.1', $detail['ip']);
        self::assertSame('Mozilla/5.0 Chrome/126', $detail['user_agent']);
        self::assertSame('内部网络', $detail['location']);
        self::assertSame('Mac 上的 Chrome', $detail['device_label']);
    }

    public function testKeywordAndStatusFilter(): void
    {
        $this->addRow(['username_input' => 'admin']);
        $this->addRow(['username_input' => 'guest', 'succeeded' => 'false', 'reason' => 'user_not_found']);
        $service = new LoginLogService($this->conn, $this->audit);

        self::assertSame(1, $service->page(['keyword' => 'guest'])['total']);
        self::assertSame(1, $service->page(['succeeded' => 'false'])['total']);
        self::assertSame(0, $service->page(['keyword' => 'guest', 'succeeded' => 'true'])['total']);
    }

    public function testDetailMissingThrowsNotFound(): void
    {
        $service = new LoginLogService($this->conn, $this->audit);
        $this->expectException(OrgException::class);
        $service->detail(999999);
    }

    public function testExportCreatesTaskAndFile(): void
    {
        $this->addRow();
        $service = new LoginLogService($this->conn, $this->audit);
        $result = $service->export([], $this->userActor(), 'req-export-1', '127.0.0.1', 'Mac');

        self::assertSame('succeeded', $result['state']);
        $task = $this->conn->fetchAssociative('SELECT type, result FROM sand_system_task_run WHERE public_id::text = ?', [$result['task_id']]);
        self::assertNotFalse($task);
        self::assertSame('login-log.export', $task['type']);
        $files = OrgSql::int($this->conn->fetchOne("SELECT COUNT(*) FROM sand_system_file WHERE purpose = 'export'"));
        self::assertSame(1, $files);
        // 导出本身落一条操作日志
        self::assertSame(1, OrgSql::int($this->conn->fetchOne("SELECT COUNT(*) FROM sand_system_audit_log WHERE action = 'export'")));
    }
}
