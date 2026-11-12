<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 定时任务下一次执行时间。结果严格晚于 $from，时区沿用 $from。
 * monthly 的 monthday 超过当月天数时按当月最后一天执行（如选 31 号，
 * 2 月落到 28/29 号，3 月恢复 31 号），与契约 next_run_time 口径一致。
 */
final class ScheduleNextRun
{
    private function __construct()
    {
    }

    public static function next(ScheduleDefinition $schedule, \DateTimeImmutable $from): \DateTimeImmutable
    {
        return match ($schedule->freq) {
            ScheduleFreq::Interval => self::interval($schedule, $from),
            ScheduleFreq::Hourly => self::hourly($schedule, $from),
            ScheduleFreq::Daily => self::daily($schedule, $from),
            ScheduleFreq::Weekly => self::weekly($schedule, $from),
            ScheduleFreq::Monthly => self::monthly($schedule, $from),
        };
    }

    private static function interval(ScheduleDefinition $schedule, \DateTimeImmutable $from): \DateTimeImmutable
    {
        // interval 为分钟数，从当前时刻顺延；freq=interval 时形状校验保证非空
        return $from->modify('+' . ($schedule->interval ?? 1) . ' minutes');
    }

    private static function hourly(ScheduleDefinition $schedule, \DateTimeImmutable $from): \DateTimeImmutable
    {
        // 每小时定点执行，time 只取分钟位（HH:MM 中的 MM）
        [, $minute] = self::clock($schedule);
        $candidate = $from->setTime((int) $from->format('H'), $minute, 0);
        if ($candidate <= $from) {
            $candidate = $candidate->modify('+1 hour');
        }

        return $candidate;
    }

    private static function daily(ScheduleDefinition $schedule, \DateTimeImmutable $from): \DateTimeImmutable
    {
        [$hour, $minute] = self::clock($schedule);
        $candidate = $from->setTime($hour, $minute, 0);
        if ($candidate <= $from) {
            $candidate = $candidate->modify('+1 day');
        }

        return $candidate;
    }

    private static function weekly(ScheduleDefinition $schedule, \DateTimeImmutable $from): \DateTimeImmutable
    {
        // weekday 取 ISO-8601 口径：1=周一 … 7=周日，与 date('N') 一致；
        // freq=weekly 时形状校验保证非空
        [$hour, $minute] = self::clock($schedule);
        $days = ((($schedule->weekday ?? 1) - (int) $from->format('N')) % 7 + 7) % 7;
        $candidate = $from->modify('+' . $days . ' days')->setTime($hour, $minute, 0);
        if ($candidate <= $from) {
            $candidate = $candidate->modify('+7 days');
        }

        return $candidate;
    }

    private static function monthly(ScheduleDefinition $schedule, \DateTimeImmutable $from): \DateTimeImmutable
    {
        [$hour, $minute] = self::clock($schedule);
        $candidate = self::clampDay($from, $schedule->monthday)->setTime($hour, $minute, 0);
        if ($candidate <= $from) {
            $nextMonth = $from->modify('first day of next month');
            $candidate = self::clampDay($nextMonth, $schedule->monthday)->setTime($hour, $minute, 0);
        }

        return $candidate;
    }

    /** monthday 超过当月天数时钳到当月最后一天。 */
    private static function clampDay(\DateTimeImmutable $month, ?int $monthday): \DateTimeImmutable
    {
        $day = min($monthday ?? 1, (int) $month->format('t'));

        return $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day);
    }

    /** @return array{0: int, 1: int} time 的时与分；time 已由 ScheduleDefinition 保证 HH:MM。 */
    private static function clock(ScheduleDefinition $schedule): array
    {
        $parts = explode(':', $schedule->time ?? '00:00');

        return [(int) $parts[0], (int) $parts[1]];
    }
}
