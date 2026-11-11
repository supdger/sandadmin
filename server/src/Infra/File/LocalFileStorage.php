<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\File;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Sandadmin\Domain\File\FileException;

/**
 * 1.0 只接本机磁盘，直接用 Flysystem Local adapter（§3.8.4/§4.3，不再自定义存储接口）。
 * 对象键是随机生成的 storage_key，原始文件名只写 sand_system_file 元数据；
 * 存储根在 web 根之外（config/file.php 默认 runtime/files）。
 */
final class LocalFileStorage
{
    public const KEY_PATTERN = '/\A[a-f0-9]{32}\z/';

    private function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $root,
    ) {
    }

    public static function open(string $root): self
    {
        if ($root === '') {
            throw FileException::internal('存储配置无效');
        }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw FileException::internal('无法创建存储目录');
        }
        $resolved = realpath($root);
        if ($resolved === false || !is_dir($resolved) || !is_writable($resolved)) {
            throw FileException::internal('存储目录不可用');
        }
        $adapter = new LocalFilesystemAdapter(
            $resolved,
            null,
            LOCK_EX,
            LocalFilesystemAdapter::DISALLOW_LINKS,
        );

        return new self(new Filesystem($adapter), $resolved);
    }

    /**
     * 流式写入并返回随机对象键；键冲突时重试。
     *
     * @param resource $stream
     */
    public function writeStream(mixed $stream): string
    {
        if (!is_resource($stream)) {
            throw FileException::invalid(['file' => ['上传内容不是可读流']]);
        }
        if (!rewind($stream)) {
            throw FileException::internal('无法读取上传内容');
        }
        try {
            $key = $this->allocateKey();
            $this->filesystem->writeStream($key, $stream);
        } catch (FilesystemException $error) {
            throw FileException::internal('文件写入失败', $error);
        }

        return $key;
    }

    public function exists(string $key): bool
    {
        self::assertKey($key);
        try {
            return $this->filesystem->fileExists($key);
        } catch (FilesystemException $error) {
            throw FileException::internal('无法读取存储', $error);
        }
    }

    public function delete(string $key): void
    {
        self::assertKey($key);
        try {
            if (!$this->filesystem->fileExists($key)) {
                return;
            }
            $this->filesystem->delete($key);
        } catch (FilesystemException $error) {
            throw FileException::internal('文件删除失败', $error);
        }
    }

    /** 本机适配器下对象键即根目录内相对路径；仅下载响应使用。 */
    public function localPath(string $key): string
    {
        self::assertKey($key);

        return $this->root . DIRECTORY_SEPARATOR . $key;
    }

    private static function assertKey(string $key): void
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw FileException::internal('存储键无效');
        }
    }

    private function allocateKey(): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $key = bin2hex(random_bytes(16));
            if (!$this->filesystem->fileExists($key)) {
                return $key;
            }
        }

        throw FileException::internal('无法分配文件存储键');
    }
}
