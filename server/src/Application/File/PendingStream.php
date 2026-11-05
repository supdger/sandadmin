<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Storage\Exception\StorageException;

final class PendingStream
{
    /** @var resource */
    private mixed $stream;

    private bool $open = true;

    /**
     * @param resource $stream
     */
    public function __construct(mixed $stream, public readonly int $size)
    {
        if (!is_resource($stream)) {
            throw new StorageException('invalid_upload', '上传内容不是可读流', ['file' => '上传内容不是可读流']);
        }
        $this->stream = $stream;
    }

    /**
     * @return resource
     */
    public function stream(): mixed
    {
        if (!$this->open || !is_resource($this->stream)) {
            throw new StorageException('storage_unavailable', '上传内容已关闭');
        }

        return $this->stream;
    }

    public function close(): void
    {
        if ($this->open && is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->open = false;
    }
}
