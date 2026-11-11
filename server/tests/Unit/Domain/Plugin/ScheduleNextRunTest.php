<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\ScheduleDefinition;
use Sandadmin\Domain\Plugin\ScheduleNextRun;

final class ScheduleNextRunTest extends TestCase
{
    /** @param array<string, mixed> $data */
    private static function schedule(array $data): ScheduleDefinition
    {
        return ScheduleDefinition::fromArray($data);
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }

    /** @param array<string, mixed> $data */
    private static function next(array $data, string $from): string
    {
        return ScheduleNextRun::next(self::schedule($data), self::at($from))->format('Y-m-d H:i:s');
    }

    public function testIntervalAddsMinutesFromNow(): void
    {
        self::assertSame(
            '2026-01-01 10:15:00',
            self::next(['freq' => 'interval', 'interval' => 15], '2026-01-01 10:00:00'),
        );
    }

    public function testHourlyUsesMinutePart(): void
    {
        $schedule = ['freq' => 'hourly', 'time' => '00:30'];
        self::assertSame('2026-01-01 10:30:00', self::next($schedule, '2026-01-01 10:10:00'));
        // 整点时刻本身不算未来，顺延到下一小时
        self::assertSame('2026-01-01 11:30:00', self::next($schedule, '2026-01-01 10:30:00'));
        self::assertSame('2026-01-01 23:30:00', self::next($schedule, '2026-01-01 23:00:00'));
        self::assertSame('2026-01-02 00:30:00', self::next($schedule, '2026-01-01 23:45:00'));
    }

    public function testDailyAtClockTime(): void
    {
        $schedule = ['freq' => 'daily', 'time' => '03:00'];
        self::assertSame('2026-01-01 03:00:00', self::next($schedule, '2026-01-01 02:59:00'));
        self::assertSame('2026-01-02 03:00:00', self::next($schedule, '2026-01-01 03:00:00'));
    }

    public function testWeeklyIsoWeekday(): void
    {
        // 2026-01-04 是周日（N=7），下一个周一（weekday=1）是 01-05
        $schedule = ['freq' => 'weekly', 'time' => '09:00', 'weekday' => 1];
        self::assertSame('2026-01-05 09:00:00', self::next($schedule, '2026-01-04 10:00:00'));
        // 当天已过点 → 下周同一天
        self::assertSame('2026-01-12 09:00:00', self::next($schedule, '2026-01-05 09:30:00'));
        // weekday=7 即周日
        $sunday = ['freq' => 'weekly', 'time' => '09:00', 'weekday' => 7];
        self::assertSame('2026-01-11 09:00:00', self::next($sunday, '2026-01-05 00:00:00'));
    }

    public function testMonthlyClampsToShortMonth(): void
    {
        $schedule = ['freq' => 'monthly', 'time' => '10:00', 'monthday' => 31];
        // 2026-02 平年 28 天：31 号钳到 28 号
        self::assertSame('2026-02-28 10:00:00', self::next($schedule, '2026-02-10 00:00:00'));
        // 2 月 28 日 10:00 已过 → 3 月恢复 31 号
        self::assertSame('2026-03-31 10:00:00', self::next($schedule, '2026-02-28 10:00:00'));
        // 30 天月（4 月）选 31 号 → 4 月 30 日
        self::assertSame('2026-04-30 10:00:00', self::next($schedule, '2026-04-01 00:00:00'));
        // 31 天月正常落点
        self::assertSame('2026-05-31 10:00:00', self::next($schedule, '2026-05-01 00:00:00'));
    }

    public function testMonthlyClampsLeapFebruary(): void
    {
        $thirty = ['freq' => 'monthly', 'time' => '10:00', 'monthday' => 30];
        // 2028 闰年 2 月 29 天：30 号钳到 29 号
        self::assertSame('2028-02-29 10:00:00', self::next($thirty, '2028-02-01 00:00:00'));
        // 平年 2 月：30 号钳到 28 号
        self::assertSame('2026-02-28 10:00:00', self::next($thirty, '2026-02-01 00:00:00'));
        $twentynine = ['freq' => 'monthly', 'time' => '10:00', 'monthday' => 29];
        // 闰年 29 号正常落点；平年钳到 28 号
        self::assertSame('2028-02-29 10:00:00', self::next($twentynine, '2028-02-01 00:00:00'));
        self::assertSame('2026-02-28 10:00:00', self::next($twentynine, '2026-02-01 00:00:00'));
    }

    public function testNextRunIsStrictlyFutureAcrossFreqs(): void
    {
        $from = self::at('2026-06-15 12:34:56');
        $cases = [
            ['freq' => 'interval', 'interval' => 5],
            ['freq' => 'hourly', 'time' => '00:34'],
            ['freq' => 'daily', 'time' => '12:34'],
            ['freq' => 'weekly', 'time' => '12:34', 'weekday' => 1],
            ['freq' => 'monthly', 'time' => '12:34', 'monthday' => 31],
        ];
        foreach ($cases as $data) {
            $next = ScheduleNextRun::next(self::schedule($data), $from);
            self::assertGreaterThan($from, $next, $data['freq']);
        }
    }
}
