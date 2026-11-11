<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Application\File\BoundedCopier;
use Sandadmin\Application\File\FileService;
use Sandadmin\Application\File\StorageConfig;
use Sandadmin\Application\File\UploadService;
use Sandadmin\Infra\File\FinfoMimeSniffer;
use Sandadmin\Infra\File\LocalFileStorage;
use Sandadmin\Tests\Support\FakeCleanupQueue;
use Sandadmin\Tests\Support\FakeFileRepository;
use Sandadmin\Tests\Support\FakeIdempotencyStore;

/** 单元测试装配：真实 Flysystem 本地存储（临时目录）+ 内存仓储。 */
final class FileKit
{
    public static function scratchRoot(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sand-file-test-' . bin2hex(random_bytes(6));

        return $root;
    }

    public static function removeTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $path = $item->getPathname();
            if ($item->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($root);
    }

    public static function storage(string $root): LocalFileStorage
    {
        return LocalFileStorage::open($root);
    }

    public static function config(int $maxBytes = StorageConfig::DEFAULT_MAX_BYTES): StorageConfig
    {
        return new StorageConfig(
            root: '/unused',
            maxBytes: $maxBytes,
            allowedMimeTypes: [
                'image/png',
                'image/jpeg',
                'application/pdf',
                'text/plain',
                'application/zip',
            ],
            allowedExtensions: ['png', 'jpg', 'jpeg', 'pdf', 'txt', 'zip'],
        );
    }

    public static function uploads(
        LocalFileStorage $storage,
        FakeFileRepository $files,
        ?FakeIdempotencyStore $idempotency = null,
        int $maxBytes = StorageConfig::DEFAULT_MAX_BYTES,
    ): UploadService {
        return new UploadService(
            $storage,
            $files,
            self::config($maxBytes),
            new FinfoMimeSniffer(),
            new BoundedCopier(),
            $idempotency,
        );
    }

    public static function service(
        FakeFileRepository $files,
        LocalFileStorage $storage,
        ?FakeCleanupQueue $cleanup = null,
        ?FakeIdempotencyStore $idempotency = null,
    ): FileService {
        return new FileService($files, $storage, $cleanup ?? new FakeCleanupQueue(), $idempotency);
    }

    /**
     * @return resource
     */
    public static function stream(string $content): mixed
    {
        $stream = fopen('php://temp', 'rb+');
        if ($stream === false) {
            throw new \RuntimeException('无法创建测试流');
        }
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }
}
