<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Storage\Exception\InvalidUploadException;
use Sandadmin\Storage\Exception\StorageException;
use Sandadmin\Storage\Exception\UploadTooLargeException;

/** 按块复制上传流，超过上限即停止，不把整个文件读进一个字符串。 */
final class BoundedCopier
{
    public const CHUNK_BYTES = 8192;

    /**
     * @param resource $input
     */
    public function copy(mixed $input, int $maxBytes): PendingStream
    {
        if (!is_resource($input)) {
            throw new InvalidUploadException(['file' => '上传内容不是可读流']);
        }
        if ($maxBytes < 1) {
            throw new StorageException('invalid_config', '存储配置无效');
        }
        $tmp = fopen('php://temp/maxmemory:1048576', 'wb+');
        if ($tmp === false) {
            throw new StorageException('storage_unavailable', '无法创建临时文件');
        }
        $written = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);
                if ($chunk === false) {
                    throw new StorageException('storage_unavailable', '读取上传内容失败');
                }
                if ($chunk === '') {
                    break;
                }
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    throw new UploadTooLargeException($maxBytes);
                }
                if (fwrite($tmp, $chunk) === false) {
                    throw new StorageException('storage_unavailable', '写入临时文件失败');
                }
            }
        } catch (\Throwable $e) {
            fclose($tmp);
            throw $e;
        }
        if ($written === 0) {
            fclose($tmp);
            throw new InvalidUploadException(['file' => '不能上传空文件']);
        }
        if (!rewind($tmp)) {
            fclose($tmp);
            throw new StorageException('storage_unavailable', '无法读取临时文件');
        }

        return new PendingStream($tmp, $written);
    }
}
