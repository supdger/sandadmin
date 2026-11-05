<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class WorkbenchCard implements \JsonSerializable
{
    public string $pluginId;
    public string $key;
    public string $title;
    public string $component;
    public int $width;
    public int $height;
    public int $sortOrder;

    public function __construct(
        string $pluginId,
        string $key,
        string $title,
        string $component,
        int $width,
        int $height,
        int $sortOrder,
    ) {
        $this->pluginId = ExtensionRules::pluginId($pluginId);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->component = ExtensionRules::component($component);
        $this->width = ExtensionRules::span($width, 'width');
        $this->height = ExtensionRules::span($height, 'height');
        $this->sortOrder = ExtensionRules::sortOrder($sortOrder);
    }

    /** @return array{plugin_id: string, key: string, title: string, component: string, width: int, height: int, sort_order: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_id' => $this->pluginId,
            'key' => $this->key,
            'title' => $this->title,
            'component' => $this->component,
            'width' => $this->width,
            'height' => $this->height,
            'sort_order' => $this->sortOrder,
        ];
    }
}
