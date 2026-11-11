<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 插件声明的定时任务。定义（处理类、默认时间结构）只来自签名清单；
 * 启停与时间覆盖另存 sand_system_schedule，本对象不调度、不实例化处理类。
 */
final readonly class CronTask implements \JsonSerializable
{
    public string $pluginCode;
    public string $key;
    public string $title;
    public string $handler;
    public ScheduleDefinition $schedule;
    public ?string $description;
    public bool $scheduleLocked;
    public bool $isCritical;
    public int $sort;

    public function __construct(
        string $pluginCode,
        string $key,
        string $title,
        string $handler,
        ScheduleDefinition $schedule,
        ?string $description,
        bool $scheduleLocked,
        bool $isCritical,
        int $sort,
    ) {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->handler = ExtensionRules::className($handler, 'handler');
        $this->schedule = $schedule;
        $this->description = $description === null ? null : ExtensionRules::description($description);
        $this->scheduleLocked = $scheduleLocked;
        $this->isCritical = $isCritical;
        $this->sort = ExtensionRules::sort($sort);
    }

    /** @return array{plugin_code: string, key: string, title: string, handler: string, schedule: array<string, mixed>, description: string|null, schedule_locked: bool, is_critical: bool, sort: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_code' => $this->pluginCode,
            'key' => $this->key,
            'title' => $this->title,
            'handler' => $this->handler,
            'schedule' => $this->schedule->jsonSerialize(),
            'description' => $this->description,
            'schedule_locked' => $this->scheduleLocked,
            'is_critical' => $this->isCritical,
            'sort' => $this->sort,
        ];
    }
}
