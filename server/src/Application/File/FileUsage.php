<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Storage\Exception\FileInUseException;
use Sandadmin\Storage\Exception\FileNotFoundException;
use Sandadmin\Storage\FileId;
use Sandadmin\Storage\FileStore;
use Sandadmin\Storage\FileUsageList;
use Sandadmin\Storage\FileUsageRef;
use Sandadmin\Storage\FileUsageRegistry;

/** 文件引用登记。register / unregister 幂等；仍被引用的文件不能删除。 */
final class FileUsage
{
    public function __construct(
        private readonly FileStore $files,
        private readonly FileUsageRegistry $usages,
    ) {
    }

    public function register(FileUsageRef $ref): void
    {
        if (!$this->files->exists($ref->fileId)) {
            throw new FileNotFoundException();
        }
        $this->usages->register($ref);
    }

    public function unregister(FileUsageRef $ref): void
    {
        $this->usages->unregister($ref);
    }

    public function deleteFile(string $fileId): void
    {
        FileId::assert($fileId);
        if ($this->usages->countFor($fileId) > 0) {
            throw new FileInUseException();
        }
        $this->files->delete($fileId);
    }

    public function list(string $fileId): FileUsageList
    {
        FileId::assert($fileId);

        return $this->usages->listFor($fileId);
    }
}
