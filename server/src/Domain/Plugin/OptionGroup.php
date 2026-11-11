<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class OptionGroup implements \JsonSerializable
{
    public string $pluginCode;
    public string $key;
    public string $title;

    /** @var list<OptionItem> */
    public array $options;

    public int $sort;

    /** @param list<OptionItem> $options */
    public function __construct(string $pluginCode, string $key, string $title, array $options, int $sort)
    {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->options = ExtensionRules::options($options);
        $this->sort = ExtensionRules::sort($sort);
    }

    /** @return array{plugin_code: string, key: string, title: string, options: list<array{value: string, label: string}>, sort: int} */
    public function jsonSerialize(): array
    {
        $options = [];
        foreach ($this->options as $option) {
            $options[] = $option->jsonSerialize();
        }

        return [
            'plugin_code' => $this->pluginCode,
            'key' => $this->key,
            'title' => $this->title,
            'options' => $options,
            'sort' => $this->sort,
        ];
    }
}
