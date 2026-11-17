<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 插件 manifest `locales` 段：相对插件目录的语言包目录。 */
final readonly class PluginLocaleSpec
{
    public function __construct(
        public ?string $backend,
        public ?string $frontend,
    ) {
    }
}
