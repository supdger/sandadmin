<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileRepository;
use Sandadmin\Domain\File\FileUsage;

/**
 * sand_system_file_usage 的登记入口，供业务模块在事务内引用/取消引用文件。
 * register 幂等；引用行与业务数据的事务一致性由调用方保证。
 */
final class UsageService
{
    public function __construct(private readonly FileRepository $files)
    {
    }

    /** 登记引用；文件不存在或已删除时报 FILE_NOT_FOUND。 */
    public function register(FileUsage $usage): void
    {
        if ($this->files->findById($usage->fileId) === null) {
            throw FileException::notFound();
        }
        $this->files->addUsage($usage);
    }

    public function unregister(FileUsage $usage): void
    {
        $this->files->removeUsage($usage);
    }

    /** @return list<FileUsage> */
    public function listFor(int $fileId): array
    {
        return $this->files->usagesFor($fileId);
    }
}
