<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 插件 manifest 中宿主消费的顶层声明段（菜单、语言包）。
 * 清单缺失或不合法时由装载器产出空 manifest，扩展位段仍归 ExtensionManifest。
 */
final readonly class PluginManifest
{
    /** @param list<PluginMenuEntry> $menus */
    public function __construct(
        public string $code,
        public array $menus,
        public ?PluginLocaleSpec $locales,
    ) {
    }

    public static function empty(string $code): self
    {
        return new self($code, [], null);
    }

    public function declaresMenus(): bool
    {
        return $this->menus !== [];
    }
}
