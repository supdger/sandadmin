<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Domain\File\FileException;

/** config/file.php 的值对象：存储根、大小上限、扩展名与 MIME 双白名单。 */
final readonly class StorageConfig
{
    public const DEFAULT_MAX_BYTES = 104857600;

    public const MIME_PATTERN = '/\A[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*\z/';

    private const EXTENSION_PATTERN = '/\A[a-z0-9]{1,16}\z/';

    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        public string $root,
        public int $maxBytes,
        public array $allowedMimeTypes,
        public array $allowedExtensions,
    ) {
        if ($root === '' || $maxBytes < 1) {
            throw FileException::internal('存储配置无效');
        }
        foreach ($allowedMimeTypes as $mime) {
            if (preg_match(self::MIME_PATTERN, $mime) !== 1) {
                throw FileException::internal('存储配置包含无效的文件类型');
            }
        }
        foreach ($allowedExtensions as $extension) {
            if (preg_match(self::EXTENSION_PATTERN, $extension) !== 1) {
                throw FileException::internal('存储配置包含无效的扩展名');
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
        $rawMimes = $config['allowed_mime_types'] ?? null;
        $rawExtensions = $config['allowed_extensions'] ?? null;
        if (!is_string($root) || !is_int($maxBytes) || !is_array($rawMimes) || !is_array($rawExtensions)) {
            throw FileException::internal('存储配置无效');
        }
        $mimes = [];
        foreach ($rawMimes as $mime) {
            if (!is_string($mime)) {
                throw FileException::internal('存储配置包含无效的文件类型');
            }
            $mimes[] = strtolower($mime);
        }
        $extensions = [];
        foreach ($rawExtensions as $extension) {
            if (!is_string($extension)) {
                throw FileException::internal('存储配置包含无效的扩展名');
            }
            $extensions[] = strtolower($extension);
        }

        return new self($root, $maxBytes, $mimes, $extensions);
    }

    /** @return list<string> */
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

    /** @return list<string> */
    public static function defaultAllowedExtensions(): array
    {
        return [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'txt', 'csv', 'md',
            'zip', 'xlsx', 'docx',
        ];
    }
}
