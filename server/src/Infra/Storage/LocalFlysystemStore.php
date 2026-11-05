<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Sandadmin\Storage\Exception\FileNotFoundException;
use Sandadmin\Storage\Exception\InvalidUploadException;
use Sandadmin\Storage\Exception\StorageException;
use Sandadmin\Storage\FileId;
use Sandadmin\Storage\FileStore;
use Sandadmin\Storage\StorageConfig;
use Sandadmin\Storage\StoredFile;

/** 1.0 只接本机磁盘。对象键是生成的文件标识，调用方给出的文件名只写入元数据。 */
final class LocalFlysystemStore implements FileStore
{
    private function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public static function open(string $root): self
    {
        if ($root === '') {
            throw new StorageException('invalid_config', '存储配置无效');
        }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new StorageException('storage_unavailable', '无法创建存储目录');
        }
        $real = realpath($root);
        if ($real === false || !is_dir($real) || !is_writable($real)) {
            throw new StorageException('storage_unavailable', '存储目录不可用');
        }
        $adapter = new LocalFilesystemAdapter(
            $real,
            null,
            LOCK_EX,
            LocalFilesystemAdapter::DISALLOW_LINKS,
        );

        return new self(new Filesystem($adapter));
    }

    public function write(mixed $stream, int $size, string $mime, string $originalName): StoredFile
    {
        if (!is_resource($stream)) {
            throw new InvalidUploadException(['file' => '上传内容不是可读流']);
        }
        if ($size < 1 || preg_match(StorageConfig::MIME_PATTERN, $mime) !== 1) {
            throw new InvalidUploadException(['file' => '上传内容无效']);
        }
        if (!rewind($stream)) {
            throw new StorageException('storage_unavailable', '无法读取上传内容');
        }
        try {
            $id = $this->allocateId();
            $createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $stored = new StoredFile($id, $size, $mime, $originalName, $createdAt);
            $this->filesystem->writeStream($id, $stream);
            try {
                $this->filesystem->write($id . '.meta.json', $this->encodeMeta($stored));
            } catch (FilesystemException $metaError) {
                $this->filesystem->delete($id);
                throw $metaError;
            }

            return $stored;
        } catch (FilesystemException $error) {
            throw new StorageException('storage_unavailable', '文件写入失败', [], $error);
        }
    }

    public function exists(string $fileId): bool
    {
        if (preg_match(FileId::PATTERN, $fileId) !== 1) {
            return false;
        }
        try {
            return $this->filesystem->fileExists($fileId)
                && $this->filesystem->fileExists($fileId . '.meta.json');
        } catch (FilesystemException $error) {
            throw new StorageException('storage_unavailable', '无法读取存储', [], $error);
        }
    }

    public function get(string $fileId): StoredFile
    {
        FileId::assert($fileId);
        try {
            if (!$this->filesystem->fileExists($fileId) || !$this->filesystem->fileExists($fileId . '.meta.json')) {
                throw new FileNotFoundException();
            }
            $raw = $this->filesystem->read($fileId . '.meta.json');
        } catch (FileNotFoundException $error) {
            throw $error;
        } catch (FilesystemException $error) {
            throw new StorageException('storage_unavailable', '无法读取存储', [], $error);
        }

        return $this->decodeMeta($fileId, $raw);
    }

    public function delete(string $fileId): void
    {
        FileId::assert($fileId);
        try {
            if (!$this->filesystem->fileExists($fileId)) {
                throw new FileNotFoundException();
            }
            $this->filesystem->delete($fileId);
            $meta = $fileId . '.meta.json';
            if ($this->filesystem->fileExists($meta)) {
                $this->filesystem->delete($meta);
            }
        } catch (FileNotFoundException $error) {
            throw $error;
        } catch (FilesystemException $error) {
            throw new StorageException('storage_unavailable', '文件删除失败', [], $error);
        }
    }

    private function allocateId(): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $id = bin2hex(random_bytes(16));
            if (!$this->filesystem->fileExists($id)) {
                return $id;
            }
        }

        throw new StorageException('storage_unavailable', '无法分配文件标识');
    }

    private function encodeMeta(StoredFile $file): string
    {
        return json_encode([
            'id' => $file->id,
            'size' => $file->size,
            'mime' => $file->mime,
            'original_name' => $file->originalName,
            'created_at' => $file->createdAt->format('Y-m-d\TH:i:s.uP'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function decodeMeta(string $fileId, string $raw): StoredFile
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new StorageException('storage_unavailable', '文件元数据损坏');
        }
        $id = $data['id'] ?? null;
        $size = $data['size'] ?? null;
        $mime = $data['mime'] ?? null;
        $name = $data['original_name'] ?? null;
        $created = $data['created_at'] ?? null;
        $sizeValue = null;
        if (is_int($size)) {
            $sizeValue = $size;
        } elseif (is_string($size) && ctype_digit($size)) {
            $sizeValue = (int) $size;
        }
        if (!is_string($id) || $sizeValue === null || $sizeValue < 1 || !is_string($mime) || !is_string($name) || !is_string($created) || $id !== $fileId) {
            throw new StorageException('storage_unavailable', '文件元数据损坏');
        }
        try {
            $createdAt = new \DateTimeImmutable($created);
        } catch (\Exception) {
            throw new StorageException('storage_unavailable', '文件元数据损坏');
        }

        return new StoredFile($id, $sizeValue, $mime, $name, $createdAt);
    }
}
