<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Monitor;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;

/**
 * sand_system_metric_sample 的列名与 ServerStatus 接口字段路径逐字同名
 * （openapi.yaml：collected_time、cpu.*、memory.*、disk.*、process.*）。
 * db_ok 是采样时的数据库连通性记录，不属于接口字段。
 * 需要 SAND_TEST_DB_URL 指向已应用 0001 与 0007 的 PostgreSQL。
 */
final class MetricSampleColumnsTest extends TestCase
{
    public function testColumnsMatchServerStatusFields(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        $conn = DriverManager::getConnection((new DsnParser())->parse($url));
        try {
            if ($conn->fetchOne("SELECT to_regclass('sand_system_metric_sample')") === null) {
                self::markTestSkipped('需要已应用 0001 基线的 PostgreSQL');
            }
            $columns = $conn->fetchFirstColumn(
                "SELECT column_name FROM information_schema.columns"
                . " WHERE table_schema = 'public' AND table_name = 'sand_system_metric_sample'"
                . " ORDER BY column_name",
            );
            self::assertSame([
                'collected_time',
                'cpu_cores',
                'cpu_usage_percent',
                'db_ok',
                'disk_free_gb',
                'disk_total_gb',
                'memory_total_mb',
                'memory_usage_percent',
                'memory_used_mb',
                'process_memory_mb',
                'process_uptime_seconds',
            ], $columns);
        } finally {
            $conn->close();
        }
    }
}
