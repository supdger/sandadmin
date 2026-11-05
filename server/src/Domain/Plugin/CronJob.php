<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 定时任务声明。本对象只保存表达式与处理类名，不调度、不加载该类。 */
final readonly class CronJob implements \JsonSerializable
{
    public string $pluginId;
    public string $key;
    public string $title;
    public string $expression;
    public string $handler;
    public string $timezone;
    public int $sortOrder;

    public function __construct(
        string $pluginId,
        string $key,
        string $title,
        string $expression,
        string $handler,
        string $timezone,
        int $sortOrder,
    ) {
        $this->pluginId = ExtensionRules::pluginId($pluginId);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->expression = ExtensionRules::cronExpression($expression);
        $this->handler = ExtensionRules::className($handler, 'handler');
        $this->timezone = ExtensionRules::timezone($timezone);
        $this->sortOrder = ExtensionRules::sortOrder($sortOrder);
    }

    /** @return array{plugin_id: string, key: string, title: string, expression: string, handler: string, timezone: string, sort_order: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_id' => $this->pluginId,
            'key' => $this->key,
            'title' => $this->title,
            'expression' => $this->expression,
            'handler' => $this->handler,
            'timezone' => $this->timezone,
            'sort_order' => $this->sortOrder,
        ];
    }
}
