<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Storage\Exception\InvalidUploadException;
use Sandadmin\Storage\Exception\MimeNotAllowedException;
use Sandadmin\Storage\Exception\UploadTooLargeException;
use Sandadmin\Storage\FileStore;
use Sandadmin\Storage\MimeSniffer;
use Sandadmin\Storage\OriginalName;
use Sandadmin\Storage\StorageConfig;
use Sandadmin\Storage\StoredFile;

final class UploadService
{
    public function __construct(
        private readonly FileStore $files,
        private readonly StorageConfig $config,
        private readonly MimeSniffer $sniffer,
        private readonly BoundedCopier $copier,
    ) {
    }

    /**
     * @param resource $stream
     */
    public function upload(mixed $stream, ?int $contentLength, string $originalName): StoredFile
    {
        if (!is_resource($stream)) {
            throw new InvalidUploadException(['file' => '上传内容不是可读流']);
        }
        if ($contentLength !== null && $contentLength < 1) {
            throw new InvalidUploadException(['file' => '不能上传空文件']);
        }
        if ($contentLength !== null && $contentLength > $this->config->maxBytes) {
            throw new UploadTooLargeException($this->config->maxBytes);
        }
        $pending = $this->copier->copy($stream, $this->config->maxBytes);
        try {
            $mime = $this->sniffer->sniff($pending->stream());
            if (!in_array($mime, $this->config->allowedMimeTypes, true)) {
                throw new MimeNotAllowedException($mime);
            }

            return $this->files->write(
                $pending->stream(),
                $pending->size,
                $mime,
                OriginalName::sanitize($originalName),
            );
        } finally {
            $pending->close();
        }
    }
}
