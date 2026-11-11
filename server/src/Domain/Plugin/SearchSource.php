<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class SearchSource implements \JsonSerializable
{
    public string $pluginCode;
    public string $key;
    public string $title;
    public string $handler;
    public ?string $placeholder;
    public int $sort;

    public function __construct(
        string $pluginCode,
        string $key,
        string $title,
        string $handler,
        ?string $placeholder,
        int $sort,
    ) {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->handler = ExtensionRules::className($handler, 'handler');
        $this->placeholder = ExtensionRules::placeholder($placeholder);
        $this->sort = ExtensionRules::sort($sort);
    }

    /** @return array{plugin_code: string, key: string, title: string, handler: string, placeholder: string|null, sort: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_code' => $this->pluginCode,
            'key' => $this->key,
            'title' => $this->title,
            'handler' => $this->handler,
            'placeholder' => $this->placeholder,
            'sort' => $this->sort,
        ];
    }
}
