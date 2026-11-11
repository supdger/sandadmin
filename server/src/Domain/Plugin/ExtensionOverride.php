<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * sand_system_plugin_extension 的一行：管理员对某个扩展位的启停与排序覆盖。
 * 不携带扩展定义（标题、处理类等定义只在签名清单里）；无行即用清单默认值。
 */
final readonly class ExtensionOverride
{
    public string $pluginCode;
    public string $extensionKey;
    public bool $enabled;
    public int $sort;

    public function __construct(
        string $pluginCode,
        public ExtensionKind $kind,
        string $extensionKey,
        bool $enabled,
        int $sort,
    ) {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->extensionKey = ExtensionRules::key($extensionKey);
        $this->enabled = $enabled;
        $this->sort = ExtensionRules::sort($sort);
    }
}
