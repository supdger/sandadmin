<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

interface FileStore
{
    /**
     * @param resource $stream
     */
    public function write(mixed $stream, int $size, string $mime, string $originalName): StoredFile;

    public function exists(string $fileId): bool;

    public function get(string $fileId): StoredFile;

    public function delete(string $fileId): void;
}
