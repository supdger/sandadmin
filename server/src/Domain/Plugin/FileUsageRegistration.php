<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 文件使用方登记。只声明 owner_type，不写 file_usage 引用行。 */
final readonly class FileUsageRegistration implements \JsonSerializable
{
    public string $pluginId;
    public string $key;
    public string $title;
    public string $ownerType;
    public bool $multiple;
    public int $sortOrder;

    public function __construct(
        string $pluginId,
        string $key,
        string $title,
        string $ownerType,
        bool $multiple,
        int $sortOrder,
    ) {
        $this->pluginId = ExtensionRules::pluginId($pluginId);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->ownerType = ExtensionRules::ownerType($ownerType);
        $this->multiple = $multiple;
        $this->sortOrder = ExtensionRules::sortOrder($sortOrder);
    }

    /** @return array{plugin_id: string, key: string, title: string, owner_type: string, multiple: bool, sort_order: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_id' => $this->pluginId,
            'key' => $this->key,
            'title' => $this->title,
            'owner_type' => $this->ownerType,
            'multiple' => $this->multiple,
            'sort_order' => $this->sortOrder,
        ];
    }
}
