<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 宿主内置定时任务清单（与插件签名清单同口径：名字、说明、默认时间、
 * 是否重要来自这里，库里只存启停与时间覆盖）。
 * isCritical=true 的任务暂停时前端需要二次确认。
 */
final readonly class HostSchedule
{
    private function __construct(
        public string $code,
        public string $title,
        public string $description,
        public ScheduleDefinition $schedule,
        public bool $isCritical,
    ) {
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            new self(
                'host.backup',
                '数据备份',
                '每天自动备份系统数据',
                new ScheduleDefinition(ScheduleFreq::Daily, null, '03:00'),
                true,
            ),
            new self(
                'host.log-cleanup',
                '日志清理',
                '清理超过 180 天的日志和过期记录',
                new ScheduleDefinition(ScheduleFreq::Daily, null, '03:30'),
                true,
            ),
            new self(
                'host.message-cleanup',
                '消息清理',
                '清理过期的系统消息',
                new ScheduleDefinition(ScheduleFreq::Daily, null, '04:00'),
                false,
            ),
        ];
    }

    public static function find(string $code): ?self
    {
        foreach (self::all() as $item) {
            if ($item->code === $code) {
                return $item;
            }
        }

        return null;
    }
}
