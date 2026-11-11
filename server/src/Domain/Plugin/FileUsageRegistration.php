<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 文件使用方登记声明。只声明 owner_type，不写 sand_system_file_usage 引用行。 */
final readonly class FileUsageRegistration implements \JsonSerializable
{
    public string $pluginCode;
    public string $key;
    public string $title;
    public string $ownerType;
    public bool $multiple;
    public int $sort;

    public function __construct(
        string $pluginCode,
        string $key,
        string $title,
        string $ownerType,
        bool $multiple,
        int $sort,
    ) {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->ownerType = ExtensionRules::ownerType($ownerType);
        $this->multiple = $multiple;
        $this->sort = ExtensionRules::sort($sort);
    }

    /** @return array{plugin_code: string, key: string, title: string, owner_type: string, multiple: bool, sort: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_code' => $this->pluginCode,
            'key' => $this->key,
            'title' => $this->title,
            'owner_type' => $this->ownerType,
            'multiple' => $this->multiple,
            'sort' => $this->sort,
        ];
    }
}
