<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class OptionGroup implements \JsonSerializable
{
    public string $pluginId;
    public string $key;
    public string $title;

    /** @var list<OptionItem> */
    public array $options;

    public int $sortOrder;

    /** @param list<OptionItem> $options */
    public function __construct(string $pluginId, string $key, string $title, array $options, int $sortOrder)
    {
        $this->pluginId = ExtensionRules::pluginId($pluginId);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->options = ExtensionRules::options($options);
        $this->sortOrder = ExtensionRules::sortOrder($sortOrder);
    }

    /** @return array{plugin_id: string, key: string, title: string, options: list<array{value: string, label: string}>, sort_order: int} */
    public function jsonSerialize(): array
    {
        $options = [];
        foreach ($this->options as $option) {
            $options[] = $option->jsonSerialize();
        }

        return [
            'plugin_id' => $this->pluginId,
            'key' => $this->key,
            'title' => $this->title,
            'options' => $options,
            'sort_order' => $this->sortOrder,
        ];
    }
}
