<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class WorkbenchCard implements \JsonSerializable
{
    public string $pluginCode;
    public string $key;
    public string $title;
    public string $component;
    public int $width;
    public int $height;
    public int $sort;

    public function __construct(
        string $pluginCode,
        string $key,
        string $title,
        string $component,
        int $width,
        int $height,
        int $sort,
    ) {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->component = ExtensionRules::component($component);
        $this->width = ExtensionRules::span($width, 'width');
        $this->height = ExtensionRules::span($height, 'height');
        $this->sort = ExtensionRules::sort($sort);
    }

    /** @return array{plugin_code: string, key: string, title: string, component: string, width: int, height: int, sort: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_code' => $this->pluginCode,
            'key' => $this->key,
            'title' => $this->title,
            'component' => $this->component,
            'width' => $this->width,
            'height' => $this->height,
            'sort' => $this->sort,
        ];
    }
}
