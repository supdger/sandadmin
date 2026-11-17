<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Plugin;

use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * 插件后端语言包取文案入口。
 * manifest 声明 locales.backend 时，把 <插件根>/<code>/<backend>/<locale>.php
 * 按 domain=plugin-<code> 注册进独立 Translator；与宿主 messages 域不混。
 * 取值顺序：请求 locale → fallback（默认 zh_CN）→ 返回 key 本身，不抛异常。
 */
final class PluginI18n
{
    private readonly Translator $translator;

    /** @var array<string, true> */
    private array $mounted = [];

    /**
     * @param list<string> $fallbackLocales
     */
    public function __construct(
        private readonly PluginManifestLoader $manifests,
        private readonly string $pluginDir,
        string $locale = 'zh_CN',
        array $fallbackLocales = ['zh_CN'],
    ) {
        $this->translator = new Translator($locale);
        $this->translator->setFallbackLocales($fallbackLocales);
        $this->translator->addLoader('phpfile', new PhpFileLoader());
    }

    public static function domainOf(string $code): string
    {
        return 'plugin-' . $code;
    }

    /**
     * @param array<string, string> $replace {{name}} 占位替换表
     */
    public function trans(string $code, string $key, array $replace = [], ?string $locale = null): string
    {
        $this->mount($code);
        $text = $this->translator->trans($key, [], self::domainOf($code), $locale);
        if ($text === $key || $replace === []) {
            return $text;
        }
        $pairs = [];
        foreach ($replace as $name => $value) {
            $pairs['{{' . $name . '}}'] = $value;
        }

        return strtr($text, $pairs);
    }

    /** 挂进宿主 support\Translation 时用同一批文件与同 domain 调 addResource 即可。 */
    private function mount(string $code): void
    {
        if (isset($this->mounted[$code])) {
            return;
        }
        $this->mounted[$code] = true;
        $backend = $this->manifests->load($code)->locales?->backend;
        if ($backend === null) {
            return;
        }
        $dir = rtrim($this->pluginDir, '/') . '/' . $code . '/' . $backend;
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*.php');
        if ($files === false) {
            return;
        }
        foreach ($files as $file) {
            $locale = basename($file, '.php');
            try {
                $this->translator->addResource('phpfile', $file, $locale, self::domainOf($code));
            } catch (\Throwable) {
                // 单个语言文件损坏跳过，其余 locale 不受影响
            }
        }
    }
}
