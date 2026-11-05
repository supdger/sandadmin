<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

final readonly class SearchSource implements \JsonSerializable
{
    public string $pluginId;
    public string $key;
    public string $title;
    public string $handler;
    public ?string $placeholder;
    public int $sortOrder;

    public function __construct(
        string $pluginId,
        string $key,
        string $title,
        string $handler,
        ?string $placeholder,
        int $sortOrder,
    ) {
        $this->pluginId = ExtensionRules::pluginId($pluginId);
        $this->key = ExtensionRules::key($key);
        $this->title = ExtensionRules::title($title);
        $this->handler = ExtensionRules::className($handler, 'handler');
        $this->placeholder = ExtensionRules::placeholder($placeholder);
        $this->sortOrder = ExtensionRules::sortOrder($sortOrder);
    }

    /** @return array{plugin_id: string, key: string, title: string, handler: string, placeholder: string|null, sort_order: int} */
    public function jsonSerialize(): array
    {
        return [
            'plugin_id' => $this->pluginId,
            'key' => $this->key,
            'title' => $this->title,
            'handler' => $this->handler,
            'placeholder' => $this->placeholder,
            'sort_order' => $this->sortOrder,
        ];
    }
}
