<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Monitor;

use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgSql;

/**
 * M1 运行状态：GET /monitor/server 实时采一条并落样，
 * GET /monitor/series 从 sand_system_metric_sample 读曲线。
 * server_time 与 collected_time 是同一时刻，前端两处都当「最后更新时间」用。
 */
final class MonitorService
{
    private const METRIC_SQL = [
        'cpu' => 'cpu_usage_percent',
        'memory' => 'memory_usage_percent',
        'disk' => '(disk_total_gb - disk_free_gb) / NULLIF(disk_total_gb, 0) * 100',
    ];
    private const RANGE_SQL = [
        '1h' => '-1 hour',
        '6h' => '-6 hours',
        '24h' => '-24 hours',
        '7d' => '-7 days',
    ];

    /** login 指标分桶秒数：range 越大桶越粗，点数保持在十几到几十。 */
    private const LOGIN_BUCKET_SECONDS = [
        '1h' => 300,
        '6h' => 1800,
        '24h' => 3600,
        '7d' => 86400,
    ];
    private const DEFAULT_VERSION = '1.1.0';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed> */
    public function server(): array
    {
        $now = new \DateTimeImmutable();
        $sampler = new MetricSampler($this->connection);
        $sample = $sampler->collect();
        $sampler->persist($sample, $now);
        $iso = $now->format(\DateTimeInterface::ATOM);

        return [
            'collected_time' => $iso,
            'server_time' => $iso,
            'db_ok' => $sample['db_ok'],
            'db_checked_time' => $iso,
            'version' => $this->version(),
            'cpu' => [
                'usage_percent' => $sample['cpu_usage_percent'],
                'cores' => $sample['cpu_cores'],
            ],
            'memory' => [
                'usage_percent' => $sample['memory_usage_percent'],
                'used_mb' => $sample['memory_used_mb'],
                'total_mb' => $sample['memory_total_mb'],
            ],
            'disk' => [
                'free_gb' => $sample['disk_free_gb'],
                'total_gb' => $sample['disk_total_gb'],
            ],
            'process' => [
                'memory_mb' => $sample['process_memory_mb'],
                'uptime_seconds' => $sample['process_uptime_seconds'],
            ],
        ];
    }

    /** @return array{points: list<array{collected_time: string, value: float|int}>} */
    public function series(string $metric, string $range): array
    {
        if ($metric === 'login') {
            return $this->loginSeries($range);
        }
        $column = self::METRIC_SQL[$metric]
            ?? throw OrgException::invalid(['metric' => ['metric 只支持 cpu、memory、disk、login']]);
        $interval = self::RANGE_SQL[$range]
            ?? throw OrgException::invalid(['range' => ['range 只支持 1h、6h、24h、7d']]);
        $from = (new \DateTimeImmutable($interval))->format('Y-m-d H:i:sP');
        $rows = $this->connection->fetchAllAssociative(
            "SELECT collected_time, {$column} AS value FROM sand_system_metric_sample"
            . ' WHERE collected_time >= :from ORDER BY collected_time ASC LIMIT 2000',
            ['from' => $from],
        );
        $points = [];
        foreach ($rows as $row) {
            if ($row['value'] === null) {
                continue;
            }
            $points[] = [
                'collected_time' => (new \DateTimeImmutable(OrgSql::str($row['collected_time'])))->format(\DateTimeInterface::ATOM),
                'value' => round(is_numeric($row['value']) ? (float) $row['value'] : 0.0, 2),
            ];
        }

        return ['points' => $points];
    }

    /**
     * login 指标：按 range 从 sand_system_login_log 分桶统计登录次数，
     * value 为该桶次数（成功失败都算）。
     *
     * @return array{points: list<array{collected_time: string, value: int}>}
     */
    private function loginSeries(string $range): array
    {
        $bucket = self::LOGIN_BUCKET_SECONDS[$range]
            ?? throw OrgException::invalid(['range' => ['range 只支持 1h、6h、24h、7d']]);
        $from = (new \DateTimeImmutable(self::RANGE_SQL[$range]))->format('Y-m-d H:i:sP');
        $rows = $this->connection->fetchAllAssociative(
            'SELECT to_timestamp(floor(extract(epoch from login_time) / :bucket) * :bucket) AS bucket_time,'
            . ' COUNT(*) AS value FROM sand_system_login_log'
            . ' WHERE login_time >= :from GROUP BY bucket_time ORDER BY bucket_time ASC',
            ['bucket' => $bucket, 'from' => $from],
        );
        $points = [];
        foreach ($rows as $row) {
            $points[] = [
                'collected_time' => (new \DateTimeImmutable(OrgSql::str($row['bucket_time'])))->format(\DateTimeInterface::ATOM),
                'value' => OrgSql::int($row['value']),
            ];
        }

        return ['points' => $points];
    }

    /** 系统版本：设置表 host.version，没有就用发布默认值。 */
    private function version(): string
    {
        $raw = $this->connection->fetchOne(
            "SELECT value #>> '{}' FROM sand_system_setting WHERE key = 'host.version'",
        );

        return is_string($raw) && $raw !== '' ? $raw : self::DEFAULT_VERSION;
    }
}
