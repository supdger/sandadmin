<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/** sand_system_file 行的只读视图；usageState/usageLabel/uploaderName 为查询时推出的展示字段。 */
final readonly class FileRecord
{
    public function __construct(
        public int $id,
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
        public ?string $uploaderName,
        public FileUsageState $usageState,
        public ?string $usageLabel,
        public \DateTimeImmutable $createTime,
    ) {
    }

    /**
     * FileObject 契约形状；id/uploader_id 序列化为十进制字符串。
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => (string) $this->id,
            'original_name' => $this->originalName,
            'kind' => $this->kind->value,
            'media_type' => $this->mediaType,
            'size_bytes' => $this->sizeBytes,
            'usage_state' => $this->usageState->value,
            'usage_label' => $this->usageLabel,
            'visibility' => $this->visibility->value,
            'purpose' => $this->purpose->value,
            'uploader_id' => $this->uploaderId === null ? null : (string) $this->uploaderId,
            'uploader_name' => $this->uploaderName,
            'create_time' => $this->createTime->format(\DateTimeInterface::ATOM),
        ];
    }
}
