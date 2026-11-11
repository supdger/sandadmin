<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\ScheduleDefinition;
use Sandadmin\Domain\Plugin\ScheduleFreq;
use Sandadmin\Domain\Plugin\ScheduleOverride;

final class ScheduleOverrideTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, string>
     */
    private static function fields(array $data): array
    {
        try {
            ScheduleOverride::fromArray($data);
        } catch (InvalidExtensionException $e) {
            return $e->fields;
        }
        self::fail('非法覆盖写必须抛 InvalidExtensionException');
    }

    public function testBoundaryIntervalAccepted(): void
    {
        $low = ScheduleOverride::fromArray(['schedule' => ['freq' => 'interval', 'interval' => 5]]);
        self::assertSame(5, $low->schedule?->interval);
        $high = ScheduleOverride::fromArray(['schedule' => ['freq' => 'interval', 'interval' => 60]]);
        self::assertSame(60, $high->schedule?->interval);
    }

    public function testIntervalOutsideRangeRejected(): void
    {
        foreach ([0, 1, 4, 61, 120] as $interval) {
            $fields = self::fields(['schedule' => ['freq' => 'interval', 'interval' => $interval]]);
            self::assertSame('between:5,60', $fields['schedule.interval'] ?? null, "interval={$interval}");
        }
    }

    public function testManifestPathKeepsIntervalFloorOfOne(): void
    {
        // 清单声明口径不变：interval≥1 仍合法，两入口下限不同是有意的
        $definition = ScheduleDefinition::fromArray(['freq' => 'interval', 'interval' => 1]);
        self::assertSame(1, $definition->interval);
    }

    public function testWeekdayRangeEnforced(): void
    {
        foreach ([0, 8, -1] as $weekday) {
            $fields = self::fields([
                'schedule' => ['freq' => 'weekly', 'time' => '09:00', 'weekday' => $weekday],
            ]);
            self::assertSame('between:1,7', $fields['schedule.weekday'] ?? null, "weekday={$weekday}");
        }
        $ok = ScheduleOverride::fromArray([
            'schedule' => ['freq' => 'weekly', 'time' => '09:00', 'weekday' => 7],
        ]);
        self::assertSame(7, $ok->schedule?->weekday);
    }

    public function testMonthdayRangeEnforced(): void
    {
        foreach ([0, 32, -5] as $monthday) {
            $fields = self::fields([
                'schedule' => ['freq' => 'monthly', 'time' => '06:00', 'monthday' => $monthday],
            ]);
            self::assertSame('between:1,31', $fields['schedule.monthday'] ?? null, "monthday={$monthday}");
        }
        // 契约允许 1-31 都通过校验；当月没有该日由执行侧钳到最后一天
        $ok = ScheduleOverride::fromArray([
            'schedule' => ['freq' => 'monthly', 'time' => '06:00', 'monthday' => 31],
        ]);
        self::assertSame(31, $ok->schedule?->monthday);
    }

    public function testNullScheduleMeansRestoreDefault(): void
    {
        $override = ScheduleOverride::fromArray(['schedule' => null]);
        self::assertNull($override->schedule);
        self::assertNull($override->enabled);
    }

    public function testStatusMapsToEnabled(): void
    {
        self::assertTrue(ScheduleOverride::fromArray(['status' => 'enabled'])->enabled);
        self::assertFalse(ScheduleOverride::fromArray(['status' => 'disabled'])->enabled);
        $fields = self::fields(['status' => 'paused']);
        self::assertSame('enum', $fields['status'] ?? null);
    }

    public function testUnknownAndNonArrayRejected(): void
    {
        $fields = self::fields(['foo' => 1, 'schedule' => ['freq' => 'daily', 'time' => '01:00']]);
        self::assertSame('unknown', $fields['foo'] ?? null);
        $fields = self::fields(['schedule' => 'daily']);
        self::assertSame('object', $fields['schedule'] ?? null);
        $fields = self::fields(['schedule' => ['freq' => 'daily', 'time' => '25:00']]);
        self::assertSame('time', $fields['schedule.time'] ?? null);
        $fields = self::fields(['schedule' => ['freq' => 'interval', 'interval' => '5']]);
        self::assertSame('int', $fields['schedule.interval'] ?? null);
    }

    public function testFreqShapeStillEnforced(): void
    {
        $override = ScheduleOverride::fromArray([
            'schedule' => ['freq' => 'daily', 'time' => '03:00'],
            'status' => 'disabled',
        ]);
        self::assertSame(ScheduleFreq::Daily, $override->schedule?->freq);
        self::assertFalse($override->enabled);
        $fields = self::fields(['schedule' => ['freq' => 'monthly', 'time' => '06:00']]);
        self::assertSame('shape', $fields['schedule'] ?? null);
    }
}
