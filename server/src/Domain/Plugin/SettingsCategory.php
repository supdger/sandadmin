<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class SettingsCategory implements \JsonSerializable
{
    public string $pluginCode;
    public string $key;
    public string $title;
    public string $description;
    public ?string $icon;
    public int $sort;

    public function __construct(
        string $pluginCode,
        string $key,
        string $title,
        string $description,
        ?string $icon,
        int $sort,
    ) {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->description = ExtensionRules::description($description);
        $this->icon = ExtensionRules::icon($icon);
        $this->sort = ExtensionRules::sort($sort);
    }

    /** @return array{plugin_code: string, key: string, title: string, description: string, icon: string|null, sort: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_code' => $this->pluginCode,
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'sort' => $this->sort,
        ];
    }
}
