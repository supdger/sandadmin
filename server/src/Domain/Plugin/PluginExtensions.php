<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 一个插件在签名清单中声明的全部扩展位。同一插件内 (kind, key) 不允许重复。 */
final readonly class PluginExtensions
{
    public string $pluginCode;

    /** @var list<ExtensionDraft> */
    public array $extensions;

    /** @param list<ExtensionDraft> $extensions */
    public function __construct(string $pluginCode, array $extensions)
    {
        $this->pluginCode = ExtensionRules::pluginCode($pluginCode);
        $seen = [];
        foreach ($extensions as $draft) {
            $identity = $draft->kind->value . "\0" . $draft->key;
            if (isset($seen[$identity])) {
                throw new InvalidExtensionException('同一插件内扩展键重复', [
                    'key' => $draft->kind->value . ':' . $draft->key,
                ]);
            }
            $seen[$identity] = true;
        }
        $this->extensions = $extensions;
    }
}
