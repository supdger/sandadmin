<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;

/**
 * 到点可认领的定时任务。
 * 只认 enabled、尚未因卸载停用、且所属插件仍为 installed 的行；
 * 宿主任务没有 plugin_code。漏写 suspend_time 时，已卸载插件也不会被选中。
 */
final readonly class ScheduleClaim
{
    public const TABLE = 'sand_system_schedule';

    public function __construct(private Connection $connection)
    {
    }

    /** @return list<string> */
    public function dueCodes(\DateTimeImmutable $now): array
    {
        $now = $now->setTimezone(new \DateTimeZone('UTC'));
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.code FROM ' . self::TABLE . ' s'
            . ' LEFT JOIN sand_system_plugin p ON p.code = s.plugin_code'
            . ' WHERE s.enabled = TRUE'
            . ' AND s.suspend_time IS NULL'
            . ' AND s.next_run_time IS NOT NULL'
            . ' AND s.next_run_time <= ?'
            . ' AND (s.plugin_code IS NULL OR p.state = ?)'
            . ' ORDER BY s.next_run_time, s.code',
            [$now, 'installed'],
            [Types::DATETIME_IMMUTABLE, ParameterType::STRING],
        );

        $codes = [];
        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            if (!is_string($code) || $code === '') {
                throw new \UnexpectedValueException('schedule.code 必须是非空字符串');
            }
            $codes[] = $code;
        }

        return $codes;
    }
}
