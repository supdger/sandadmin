<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 把清单声明与 sand_system_plugin_extension 覆盖行合并成某类扩展位的生效清单。
 * 规则：无覆盖行用清单默认的 enabled/sort；有行用行值；只保留启用项；
 * 按 (sort, plugin_code, key) 升序。库里没有定义类字段，不参与合并。
 */
final class ExtensionResolver
{
    private function __construct()
    {
    }

    /**
     * @param list<PluginExtensions> $plugins
     * @param list<ExtensionOverride> $overrides 只应包含目标 kind 的覆盖行
     *
     * @return list<EffectiveExtension>
     */
    public static function resolve(ExtensionKind $kind, array $plugins, array $overrides): array
    {
        $byKey = [];
        foreach ($overrides as $override) {
            if ($override->kind !== $kind) {
                continue;
            }
            $byKey[$override->pluginCode . "\0" . $override->extensionKey] = $override;
        }

        $out = [];
        foreach ($plugins as $plugin) {
            foreach ($plugin->extensions as $draft) {
                if ($draft->kind !== $kind) {
                    continue;
                }
                $override = $byKey[$plugin->pluginCode . "\0" . $draft->key] ?? null;
                $enabled = $override->enabled ?? $draft->enabled;
                if (!$enabled) {
                    continue;
                }
                $out[] = new EffectiveExtension(
                    $plugin->pluginCode,
                    $draft,
                    $override->sort ?? $draft->sort,
                );
            }
        }

        usort(
            $out,
            static fn (EffectiveExtension $a, EffectiveExtension $b): int => [$a->sort, $a->pluginCode, $a->draft->key]
                <=> [$b->sort, $b->pluginCode, $b->draft->key],
        );

        return $out;
    }
}
