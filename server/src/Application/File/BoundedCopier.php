<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

use Sandadmin\Domain\File\FileException;

/** 按块复制上传流到临时文件，边复制边算 sha256；超过上限即停止。 */
final class BoundedCopier
{
    public const CHUNK_BYTES = 8192;

    /**
     * @param resource $input
     */
    public function copy(mixed $input, int $maxBytes): PendingStream
    {
        if (!is_resource($input)) {
            throw FileException::invalid(['file' => ['上传内容不是可读流']]);
        }
        if ($maxBytes < 1) {
            throw FileException::internal('存储配置无效');
        }
        $spool = fopen('php://temp/maxmemory:1048576', 'wb+');
        if ($spool === false) {
            throw FileException::internal('无法创建临时文件');
        }
        $written = 0;
        $hash = hash_init('sha256');
        try {
            while (!feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);
                if ($chunk === false) {
                    throw FileException::internal('读取上传内容失败');
                }
                if ($chunk === '') {
                    break;
                }
                $written += strlen($chunk);
                if ($written > $maxBytes) {
                    throw FileException::tooLarge();
                }
                hash_update($hash, $chunk);
                if (fwrite($spool, $chunk) === false) {
                    throw FileException::internal('写入临时文件失败');
                }
            }
        } catch (\Throwable $error) {
            fclose($spool);
            throw $error;
        }
        if ($written === 0) {
            fclose($spool);
            throw FileException::invalid(['file' => ['不能上传空文件']]);
        }
        if (!rewind($spool)) {
            fclose($spool);
            throw FileException::internal('无法读取临时文件');
        }

        return new PendingStream($spool, $written, hash_final($hash));
    }
}
