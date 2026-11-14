<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\AuditLog;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\AuditLog\OperateLogWriter;
use support\Request;

/**
 * OperateLogWriter::rowFor 的请求→audit_log 行映射：
 * GET 与取不到操作者跳过（null），敏感参数打码，字段口径与
 * OrgAudit 写入一致。用原始 HTTP 报文构造真实 Request 覆盖。
 */
final class OperateLogWriterTest extends TestCase
{
    /**
     * @param array<string, mixed> $context Request 公开属性（plugin/controller/action）
     */
    private function request(string $raw, array $context = [], string $remoteIp = '127.0.0.1'): Request
    {
        $request = new class($raw, $remoteIp) extends Request {
            public function __construct(string $buffer, private readonly string $fakeIp)
            {
                parent::__construct($buffer);
            }

            public function getRemoteIp(): string
            {
                return $this->fakeIp;
            }
        };
        foreach ($context as $name => $value) {
            $request->{$name} = $value;
        }

        return $request;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $context
     */
    private function post(string $path, string $body, array $headers = [], array $context = []): Request
    {
        $raw = "POST $path HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n";
        foreach ($headers as $name => $value) {
            $raw .= "$name: $value\r\n";
        }
        $raw .= 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;

        return $this->request($raw, $context);
    }

    public function testGetRequestReturnsNull(): void
    {
        $request = $this->request("GET /api/admin/v1/users HTTP/1.1\r\nHost: localhost\r\n\r\n");

        self::assertNull(OperateLogWriter::rowFor($request, ['id' => 1, 'username' => 'admin']));
    }

    public function testMissingActorReturnsNull(): void
    {
        $request = $this->post('/api/admin/v1/x', '{}');

        self::assertNull(OperateLogWriter::rowFor($request, []));
        self::assertNull(OperateLogWriter::rowFor($request, ['id' => 1]));
        self::assertNull(OperateLogWriter::rowFor($request, ['id' => 1, 'username' => '  ']));
    }

    public function testRowMapsContractFields(): void
    {
        $request = $this->post('/api/admin/v1/users', '{}', [
            'X-Request-Id' => 'qa-oplog-001',
        ], [
            'plugin' => '',
            'controller' => 'Sandadmin\\Http\\OrgController',
            'action' => 'usersStore',
        ]);

        $row = OperateLogWriter::rowFor($request, ['id' => 7, 'username' => 'admin']);
        self::assertNotNull($row);
        self::assertSame('user', $row['actor_type']);
        self::assertSame(7, $row['actor_id']);
        self::assertSame('admin', $row['actor_name']);
        // 宿主请求 plugin 为空，module 落到后台应用名
        self::assertSame('sandadmin', $row['module']);
        self::assertSame('usersStore', $row['action']);
        // 宿主控制器无插件 Permission 注解，与原监听器同为未命名业务
        self::assertSame('未命名业务', $row['action_text']);
        self::assertSame('request', $row['target_type']);
        self::assertSame('/api/admin/v1/users', $row['target_id']);
        self::assertSame('OrgController::usersStore', $row['target_label']);
        self::assertTrue($row['succeeded']);
        self::assertSame('127.0.0.1', $row['ip']);
        self::assertSame('内网IP', $row['location']);
        self::assertSame('qa-oplog-001', $row['request_id']);
        self::assertIsString($row['device_label']);
    }

    public function testSensitiveParamsMaskedInChanges(): void
    {
        $request = $this->post('/api/admin/v1/x', '{"name":"示例","password":"super-secret","nested":{"token":"abc"}}', [], [
            'controller' => 'C',
            'action' => 'save',
        ]);

        $row = OperateLogWriter::rowFor($request, ['id' => 1, 'username' => 'admin']);
        self::assertNotNull($row);
        self::assertIsString($row['changes']);
        $changes = json_decode($row['changes'], true);
        self::assertIsArray($changes);
        self::assertSame('示例', $changes['name']);
        self::assertSame('******', $changes['password']);
        $nested = $changes['nested'] ?? null;
        self::assertIsArray($nested);
        self::assertSame('******', $nested['token']);
        self::assertStringNotContainsString('super-secret', $row['changes']);
    }

    public function testInvalidRequestIdReplacedByGenerated(): void
    {
        $request = $this->post('/api/admin/v1/x', '{}', [
            'X-Request-Id' => 'not a valid id!',
        ], ['action' => 'save']);

        $row = OperateLogWriter::rowFor($request, ['id' => 1, 'username' => 'admin']);
        self::assertNotNull($row);
        self::assertIsString($row['request_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $row['request_id']);
    }

    public function testPluginRequestKeepsPluginModule(): void
    {
        $request = $this->post('/app/sandadmin/system/user/save', '{}', [], [
            'plugin' => 'sandadmin',
            'controller' => 'plugin\\sandadmin\\app\\controller\\system\\SystemUserController',
            'action' => 'save',
        ]);

        $row = OperateLogWriter::rowFor($request, ['id' => 1, 'username' => 'admin']);
        self::assertNotNull($row);
        self::assertSame('sandadmin', $row['module']);
        self::assertSame('save', $row['action']);
        self::assertSame('SystemUserController::save', $row['target_label']);
    }
}
