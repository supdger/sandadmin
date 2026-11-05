<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Storage;

use Sandadmin\Storage\Exception\InvalidUploadException;
use Sandadmin\Storage\Exception\StorageException;
use Sandadmin\Storage\MimeSniffer;

/** 用文件内容识别类型，不看扩展名。 */
final class FinfoMimeSniffer implements MimeSniffer
{
    public function sniff(mixed $stream): string
    {
        if (!is_resource($stream)) {
            throw new InvalidUploadException(['file' => '上传内容不是可读流']);
        }
        if (ftell($stream) !== 0 && !rewind($stream)) {
            throw new StorageException('storage_unavailable', '无法读取上传内容');
        }
        $head = fread($stream, 8192);
        if ($head === false) {
            throw new StorageException('storage_unavailable', '无法识别文件类型');
        }
        if (!rewind($stream)) {
            throw new StorageException('storage_unavailable', '无法读取上传内容');
        }
        if ($head === '') {
            throw new InvalidUploadException(['file' => '不能上传空文件']);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($head);
        if (!is_string($mime) || $mime === '') {
            throw new InvalidUploadException(['file' => '无法识别文件类型']);
        }
        $separator = strpos($mime, ';');
        if ($separator !== false) {
            $mime = substr($mime, 0, $separator);
        }
        $mime = strtolower(trim($mime));
        if ($mime === '' || $mime === 'application/x-empty' || $mime === 'inode/x-empty') {
            throw new InvalidUploadException(['file' => '不能上传空文件']);
        }

        return $mime;
    }
}
