<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Sandadmin\Domain\Plugin\ExtensionKind;

/** plugin_extension 的一行。仅在基础设施内部传递，注册表对外返回领域对象。 */
final readonly class StoredExtension
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $pluginId,
        public ExtensionKind $kind,
        public string $key,
        public string $title,
        public int $sortOrder,
        public bool $enabled,
        public array $payload,
    ) {
    }
}
