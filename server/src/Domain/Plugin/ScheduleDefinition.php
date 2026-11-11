<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 定时任务时间结构，形状与 sand_system_schedule.schedule 的 jsonb 一致：
 * {freq, interval?, time?, weekday?, monthday?}。
 * interval 为分钟数；time 为 HH:MM；weekday 1-7；monthday 1-31。
 */
final readonly class ScheduleDefinition implements \JsonSerializable
{
    public function __construct(
        public ScheduleFreq $freq,
        public ?int $interval = null,
        public ?string $time = null,
        public ?int $weekday = null,
        public ?int $monthday = null,
    ) {
        $this->assertShape();
    }

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        $allowed = ['freq', 'interval', 'time', 'weekday', 'monthday'];
        foreach ($data as $key => $_) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new InvalidExtensionException('时间结构含未知字段', ['schedule.' . (is_string($key) ? $key : '?') => 'unknown']);
            }
        }
        $rawFreq = $data['freq'] ?? null;
        if (!is_string($rawFreq)) {
            throw new InvalidExtensionException('时间结构缺少 freq', ['schedule.freq' => 'required']);
        }
        $freq = ScheduleFreq::tryFrom($rawFreq);
        if ($freq === null) {
            throw new InvalidExtensionException('频率取值无效', ['schedule.freq' => 'enum']);
        }

        return new self(
            $freq,
            self::intField($data, 'interval'),
            self::stringField($data, 'time'),
            self::intField($data, 'weekday'),
            self::intField($data, 'monthday'),
        );
    }

    /** @return array{freq: string, interval?: int, time?: string, weekday?: int, monthday?: int} */
    public function jsonSerialize(): array
    {
        $out = ['freq' => $this->freq->value];
        if ($this->interval !== null) {
            $out['interval'] = $this->interval;
        }
        if ($this->time !== null) {
            $out['time'] = $this->time;
        }
        if ($this->weekday !== null) {
            $out['weekday'] = $this->weekday;
        }
        if ($this->monthday !== null) {
            $out['monthday'] = $this->monthday;
        }

        return $out;
    }

    private function assertShape(): void
    {
        $needsInterval = $this->freq === ScheduleFreq::Interval;
        $needsTime = $this->freq !== ScheduleFreq::Interval;
        $needsWeekday = $this->freq === ScheduleFreq::Weekly;
        $needsMonthday = $this->freq === ScheduleFreq::Monthly;

        if ($needsInterval !== ($this->interval !== null)
            || $needsTime !== ($this->time !== null)
            || $needsWeekday !== ($this->weekday !== null)
            || $needsMonthday !== ($this->monthday !== null)) {
            throw new InvalidExtensionException('时间结构字段与 freq 不匹配', ['schedule' => 'shape']);
        }

        if ($this->interval !== null && $this->interval < 1) {
            throw new InvalidExtensionException('interval 必须为正整数分钟', ['schedule.interval' => 'min:1']);
        }
        if ($this->time !== null && preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $this->time) !== 1) {
            throw new InvalidExtensionException('time 必须为 HH:MM', ['schedule.time' => 'time']);
        }
        if ($this->weekday !== null && ($this->weekday < 1 || $this->weekday > 7)) {
            throw new InvalidExtensionException('weekday 必须为 1-7', ['schedule.weekday' => 'between:1,7']);
        }
        if ($this->monthday !== null && ($this->monthday < 1 || $this->monthday > 31)) {
            throw new InvalidExtensionException('monthday 必须为 1-31', ['schedule.monthday' => 'between:1,31']);
        }
    }

    /** @param array<array-key, mixed> $data */
    private static function intField(array $data, string $field): ?int
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }
        if (!is_int($data[$field])) {
            throw new InvalidExtensionException('时间结构字段必须为整数', ['schedule.' . $field => 'int']);
        }

        return $data[$field];
    }

    /** @param array<array-key, mixed> $data */
    private static function stringField(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }
        if (!is_string($data[$field])) {
            throw new InvalidExtensionException('时间结构字段必须为字符串', ['schedule.' . $field => 'string']);
        }

        return $data[$field];
    }
}
