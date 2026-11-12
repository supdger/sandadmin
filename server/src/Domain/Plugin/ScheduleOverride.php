<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 管理员对定时任务的覆盖写（契约 ScheduledTaskWrite：{schedule, status}）。
 * 与清单声明共用 ScheduleDefinition 的形状校验，但 interval 口径不同：
 * 清单默认允许 ≥1 分钟，管理员覆盖按契约收紧到 5-60，避免编辑入口绕过下限。
 * fields 错误键形如 schedule.interval，供上层映射 422 TASK_SCHEDULE_INVALID。
 */
final readonly class ScheduleOverride
{
    /** schedule 为 null 表示恢复清单默认（sand_system_schedule.schedule 写 NULL）。 */
    public ?ScheduleDefinition $schedule;

    /** enabled 为 null 表示本次不改启停；true/false 对应 status enabled/disabled。 */
    public ?bool $enabled;

    public function __construct(?ScheduleDefinition $schedule, ?bool $enabled)
    {
        $this->schedule = $schedule;
        $this->enabled = $enabled;
    }

    /** @param array<array-key, mixed> $data 请求体形状 {schedule?: object|null, status?: string} */
    public static function fromArray(array $data): self
    {
        foreach ($data as $key => $_) {
            if (!is_string($key) || !in_array($key, ['schedule', 'status'], true)) {
                throw new InvalidExtensionException('覆盖写含未知字段', [is_string($key) ? $key : '?' => 'unknown']);
            }
        }

        $schedule = null;
        $rawSchedule = $data['schedule'] ?? null;
        if ($rawSchedule !== null) {
            if (!is_array($rawSchedule)) {
                throw new InvalidExtensionException('schedule 必须为时间结构', ['schedule' => 'object']);
            }
            // interval 先按覆盖口径判，否则 ScheduleDefinition 只拒 <1，漏掉 1-4
            $rawInterval = $rawSchedule['interval'] ?? null;
            if (is_int($rawInterval) && ($rawInterval < 5 || $rawInterval > 60)) {
                throw new InvalidExtensionException('interval 必须为 5-60 分钟', ['schedule.interval' => 'between:5,60']);
            }
            $schedule = ScheduleDefinition::fromArray($rawSchedule);
        }

        $enabled = null;
        $rawStatus = $data['status'] ?? null;
        if ($rawStatus !== null) {
            if ($rawStatus === 'enabled') {
                $enabled = true;
            } elseif ($rawStatus === 'disabled') {
                $enabled = false;
            } else {
                throw new InvalidExtensionException('status 取值无效', ['status' => 'enum']);
            }
        }

        return new self($schedule, $enabled);
    }
}
