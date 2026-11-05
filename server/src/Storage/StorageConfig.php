<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

use Sandadmin\Storage\Exception\StorageException;

final readonly class StorageConfig
{
    public const DEFAULT_MAX_BYTES = 104857600;

    public const MIME_PATTERN = '/\A[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*\z/';

    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        public string $root,
        public int $maxBytes,
        public array $allowedMimeTypes,
    ) {
        if ($root === '' || $maxBytes < 1) {
            throw new StorageException('invalid_config', '存储配置无效');
        }
        foreach ($allowedMimeTypes as $mime) {
            if (preg_match(self::MIME_PATTERN, $mime) !== 1) {
                throw new StorageException('invalid_config', '存储配置包含无效的文件类型');
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $root = $config['root'] ?? null;
        $maxBytes = $config['max_bytes'] ?? null;
        $mimes = $config['allowed_mime_types'] ?? null;
        if (!is_string($root) || !is_int($maxBytes) || !is_array($mimes)) {
            throw new StorageException('invalid_config', '存储配置无效');
        }
        $normalized = [];
        foreach ($mimes as $mime) {
            if (!is_string($mime)) {
                throw new StorageException('invalid_config', '存储配置包含无效的文件类型');
            }
            $normalized[] = strtolower($mime);
        }

        return new self($root, $maxBytes, $normalized);
    }

    /**
     * @return list<string>
     */
    public static function defaultAllowedMimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'application/zip',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }
}
