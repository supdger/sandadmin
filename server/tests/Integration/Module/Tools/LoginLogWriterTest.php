<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use app\support\DevLoginGate;
use Sandadmin\Module\LoginLog\LoginLogWriter;

/**
 * user.login 的写库契约：插件旧监听器摘除后，LoginLogWriter 是唯一写入方，
 * 落库行必须满足 ck_sand_system_login_log_result（succeeded 与 reason 互斥）。
 */
final class LoginLogWriterTest extends ToolsCase
{
    /** @param array<string, mixed> $overrides */
    private function writeRow(array $overrides = []): void
    {
        LoginLogWriter::write($this->conn, array_merge([
            'user_id' => null,
            'username_input' => 'admin',
            'succeeded' => true,
            'reason' => null,
            'ip' => '127.0.0.1',
            'location' => '内部网络',
            'device_label' => '其他设备',
            'user_agent' => '',
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function lastRow(): array
    {
        // succeeded::text 拿确定字符串，绕开 PDO 对 boolean 的方言差异
        $row = $this->conn->fetchAssociative(
            'SELECT username_input, reason, ip, location, device_label, user_agent, succeeded::text AS ok'
            . ' FROM sand_system_login_log ORDER BY id DESC LIMIT 1',
        );
        self::assertNotFalse($row);

        return $row;
    }

    public function testSuccessRowStoresNullReason(): void
    {
        $this->writeRow();

        $row = $this->lastRow();
        self::assertSame('admin', $row['username_input']);
        self::assertSame('true', $row['ok']);
        self::assertNull($row['reason']);
    }

    public function testFailedRowStoresEnumReason(): void
    {
        $this->writeRow(['succeeded' => false, 'reason' => 'bad_password']);

        $row = $this->lastRow();
        self::assertSame('false', $row['ok']);
        self::assertSame('bad_password', $row['reason']);
    }

    public function testDevLoginUsernameStoredVerbatim(): void
    {
        // 开发登录的事件负载 username 就是开发账号，写库不替换不编造
        $this->writeRow(['username_input' => DevLoginGate::DEV_USERNAME]);

        self::assertSame(DevLoginGate::DEV_USERNAME, $this->lastRow()['username_input']);
    }

    public function testRequiredFieldsNeverNullWithoutAgent(): void
    {
        // 无 UA 时 user_agent 存空串、device_label 给确定标签，两列均非空
        $this->writeRow(['user_agent' => '', 'device_label' => '其他设备']);

        $row = $this->lastRow();
        self::assertSame('', $row['user_agent']);
        self::assertSame('其他设备', $row['device_label']);
        self::assertSame('内部网络', $row['location']);
        self::assertSame('127.0.0.1', $row['ip']);
    }

    public function testOversizedInputTruncatedToColumnWidth(): void
    {
        $this->writeRow(['username_input' => str_repeat('a', 100)]);

        self::assertSame(str_repeat('a', 64), $this->lastRow()['username_input']);
    }
}
