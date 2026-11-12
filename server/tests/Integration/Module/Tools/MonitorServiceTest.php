<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Tools;

use Sandadmin\Module\Monitor\MonitorService;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/** M1 运行状态：server 返回契约字段，series 校验 metric/range 并读样表。 */
final class MonitorServiceTest extends ToolsCase
{
    public function testServerReturnsContractFields(): void
    {
        $service = new MonitorService($this->conn);
        $body = $service->server();

        foreach (['collected_time', 'server_time', 'db_ok', 'version', 'cpu', 'memory', 'disk', 'process'] as $key) {
            self::assertArrayHasKey($key, $body);
        }
        // collected_time 与 server_time 是同一时刻（M1 契约注释保持一致）
        self::assertSame($body['server_time'], $body['collected_time']);
        self::assertTrue($body['db_ok']);
        foreach (['cpu' => 'usage_percent', 'memory' => 'used_mb', 'disk' => 'free_gb'] as $section => $field) {
            self::assertIsArray($body[$section]);
            self::assertArrayHasKey($field, $body[$section]);
        }
    }

    public function testServerPersistsMetricSample(): void
    {
        $service = new MonitorService($this->conn);
        $service->server();
        self::assertSame(1, OrgSql::int($this->conn->fetchOne('SELECT COUNT(*) FROM sand_system_metric_sample')));
    }

    public function testSeriesValidatesMetricAndRange(): void
    {
        $service = new MonitorService($this->conn);
        try {
            $service->series('bogus', '1h');
            self::fail('应抛 OrgException');
        } catch (OrgException $e) {
            self::assertSame(422, $e->status);
        }
        try {
            $service->series('cpu', '99y');
            self::fail('应抛 OrgException');
        } catch (OrgException $e) {
            self::assertSame(422, $e->status);
        }
    }

    public function testSeriesReadsPersistedSamples(): void
    {
        $this->conn->insert('sand_system_metric_sample', [
            'collected_time' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
            'cpu_usage_percent' => 12.5,
            'memory_usage_percent' => 40,
            'disk_free_gb' => 100,
            'db_ok' => 'true',
        ]);
        $service = new MonitorService($this->conn);
        $points = $service->series('cpu', '1h')['points'];
        self::assertNotEmpty($points);
        self::assertSame(12.5, $points[0]['value']);
    }

    public function testSeriesLoginBucketsLoginCounts(): void
    {
        $now = new \DateTimeImmutable();
        foreach ([$now->modify('-1 minute'), $now->modify('-2 minutes')] as $time) {
            $this->conn->insert('sand_system_login_log', [
                'username_input' => 'admin',
                'succeeded' => 'true',
                'ip' => '127.0.0.1',
                'location' => '内部网络',
                'device_label' => 'Mac',
                'user_agent' => 'UA',
                'login_time' => $time->format('Y-m-d H:i:sP'),
            ]);
        }
        // 两天前的一条不该进 1h 曲线
        $this->conn->insert('sand_system_login_log', [
            'username_input' => 'admin',
            'succeeded' => 'true',
            'ip' => '127.0.0.1',
            'location' => '内部网络',
            'device_label' => 'Mac',
            'user_agent' => 'UA',
            'login_time' => $now->modify('-2 days')->format('Y-m-d H:i:sP'),
        ]);

        $service = new MonitorService($this->conn);
        $points = $service->series('login', '1h')['points'];
        self::assertNotEmpty($points);
        $total = 0;
        foreach ($points as $point) {
            $total += OrgSql::int($point['value']);
        }
        self::assertSame(2, $total);
        // 7d 曲线把两天前那次也算上
        $week = 0;
        foreach ($service->series('login', '7d')['points'] as $point) {
            $week += OrgSql::int($point['value']);
        }
        self::assertSame(3, $week);
    }
}
