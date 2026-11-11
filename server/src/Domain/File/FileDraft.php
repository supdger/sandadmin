<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/** 写入 sand_system_file 的字段集；create_time/update_time 由库默认填充。 */
final readonly class FileDraft
{
    public function __construct(
        public string $originalName,
        public FileKind $kind,
        public string $mediaType,
        public string $extension,
        public int $sizeBytes,
        public string $sha256,
        public string $storageKey,
        public FileVisibility $visibility,
        public FilePurpose $purpose,
        public bool $usageTracked,
        public ?int $uploaderId,
    ) {
    }
}
