<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

final readonly class StoredFile implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public int $size,
        public string $mime,
        public string $originalName,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array{id: string, size: int, mime: string, original_name: string, created_at: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'size' => $this->size,
            'mime' => $this->mime,
            'original_name' => $this->originalName,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
