<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Setting\SettingService;

/** 系统设置：分组读取、校验字段级错误、回写后落审计、未知键拒绝。 */
final class SettingServiceTest extends ToolsCase
{
    public function testGroupsAndDefaultsRead(): void
    {
        $service = new SettingService($this->conn, $this->audit);
        $groups = $service->groups();
        $keys = array_column($groups, 'key');
        self::assertContains('basic', $keys);
        self::assertContains('login', $keys);
        self::assertContains('users', $keys);
        self::assertContains('watermark', $keys);

        $basic = $service->read('basic');
        $itemKeys = array_column($basic['items'], 'key');
        self::assertContains('site_name', $itemKeys);
        self::assertContains('site_logo', $itemKeys);
        self::assertContains('timezone', $itemKeys);
        // 默认时区
        $tz = array_values(array_filter($basic['items'], static fn (array $item): bool => $item['key'] === 'timezone'))[0];
        self::assertSame('Asia/Shanghai', $tz['value']);
        self::assertIsArray($tz['options']);
    }

    public function testUpdatePersistsAndAudits(): void
    {
        $service = new SettingService($this->conn, $this->audit);
        $service->update('basic', ['site_name' => '沙盒管理'], $this->userActor(), 'req-set-1', '127.0.0.1', 'Mac');

        $basic = $service->read('basic');
        $site = array_values(array_filter($basic['items'], static fn (array $item): bool => $item['key'] === 'site_name'))[0];
        self::assertSame('沙盒管理', $site['value']);
        $auditRow = $this->conn->fetchAssociative("SELECT module, action, target_id FROM sand_system_audit_log WHERE module = 'setting'");
        self::assertNotFalse($auditRow);
        self::assertSame('update', $auditRow['action']);
    }

    public function testUnknownGroupAndKeyRejected(): void
    {
        $service = new SettingService($this->conn, $this->audit);
        try {
            $service->read('nope');
            self::fail('应抛 OrgException');
        } catch (OrgException $e) {
            self::assertSame(404, $e->status);
        }
        try {
            $service->update('basic', ['ghost_key' => 1], $this->userActor(), 'r', null, null);
            self::fail('应抛 OrgException');
        } catch (OrgException $e) {
            self::assertSame(422, $e->status);
            self::assertSame('SETTING_KEY_UNKNOWN', $e->errorCode);
        }
    }

    public function testSelectFallbackToDefaultOnNull(): void
    {
        $service = new SettingService($this->conn, $this->audit);
        // 清空选择回落默认（下拉清空语义）
        $service->update('login', ['idle_minutes' => null], $this->userActor(), 'r', null, null);
        $login = $service->read('login');
        $idle = array_values(array_filter($login['items'], static fn (array $item): bool => $item['key'] === 'idle_minutes'))[0];
        self::assertSame(120, $idle['value']);
    }

    public function testInvalidOptionRejectedWithFieldError(): void
    {
        $service = new SettingService($this->conn, $this->audit);
        try {
            $service->update('login', ['idle_minutes' => 999], $this->userActor(), 'r', null, null);
            self::fail('应抛 OrgException');
        } catch (OrgException $e) {
            self::assertSame(422, $e->status);
            self::assertArrayHasKey('idle_minutes', $e->fields);
        }
    }
}
