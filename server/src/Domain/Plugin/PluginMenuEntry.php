<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 插件 manifest `menus` 段里的一项菜单声明。
 * key 限 [a-z0-9-]+；path 必须以 /plugin/<code> 开头；children 最多一层。
 */
final readonly class PluginMenuEntry
{
    /** @param list<PluginMenuEntry> $children */
    public function __construct(
        public string $key,
        public string $path,
        public string $title,
        public ?string $titleKey,
        public ?string $icon,
        public ?string $permission,
        public int $sort,
        public array $children,
    ) {
    }
}
