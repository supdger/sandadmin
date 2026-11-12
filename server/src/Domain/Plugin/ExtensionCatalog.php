<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/**
 * 已安装插件的扩展位声明清单。数据来自安装期由 manifest.json 编译出的静态产物，
 * 运行时只读本对象，不扫描插件目录、不读 manifest.json 原文。
 */
final readonly class ExtensionCatalog
{
    /** @var array<string, PluginExtensions> */
    private array $plugins;

    /** @param iterable<PluginExtensions> $plugins */
    public function __construct(iterable $plugins)
    {
        $map = [];
        foreach ($plugins as $plugin) {
            if (isset($map[$plugin->pluginCode])) {
                throw new InvalidExtensionException('插件编码重复', ['plugin_code' => 'unique']);
            }
            $map[$plugin->pluginCode] = $plugin;
        }
        ksort($map);
        $this->plugins = $map;
    }

    /** @return list<PluginExtensions> */
    public function plugins(): array
    {
        return array_values($this->plugins);
    }

    public function plugin(string $pluginCode): ?PluginExtensions
    {
        return $this->plugins[$pluginCode] ?? null;
    }

    /**
     * 由各插件的 manifest 内容构建清单。$manifests 形如
     * ['sand-iam' => ['settings_categories' => [...], ...], ...]；
     * 非扩展位段（菜单、权限等）由 ExtensionManifest 忽略。
     *
     * @param array<array-key, mixed> $manifests
     */
    public static function fromManifests(array $manifests): self
    {
        $plugins = [];
        foreach ($manifests as $pluginCode => $manifest) {
            if (!is_string($pluginCode)) {
                throw new InvalidExtensionException('插件编码必须为字符串', ['plugin_code' => 'string']);
            }
            if (!is_array($manifest)) {
                throw new InvalidExtensionException('插件清单必须为对象', ['plugin_code' => 'object']);
            }
            $plugins[] = new PluginExtensions(
                ExtensionRules::pluginCode($pluginCode),
                ExtensionManifest::drafts($manifest),
            );
        }

        return new self($plugins);
    }
}
