<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 清单声明与库里覆盖合并后的生效扩展：只保留启用项，sort 已是生效排序值。
 * draft 携带完整定义（含处理类名），这些定义不经数据库中转。
 */
final readonly class EffectiveExtension
{
    public string $pluginCode;
    public int $sort;

    public function __construct(
        string $pluginCode,
        public ExtensionDraft $draft,
        int $sort,
    ) {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $this->sort = ExtensionRules::sort($sort);
    }
}
