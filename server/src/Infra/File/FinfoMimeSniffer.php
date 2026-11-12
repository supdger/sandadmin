<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\File;

use Sandadmin\Domain\File\FileException;

/**
 * 用文件内容识别类型，不看扩展名（ext-fileinfo）。
 * 只读流开头 8KB 做嗅探后复位，不把整个文件读进内存。
 */
final class FinfoMimeSniffer
{
    public function sniff(mixed $stream): string
    {
        if (!is_resource($stream)) {
            throw FileException::invalid(['file' => ['上传内容不是可读流']]);
        }
        if (ftell($stream) !== 0 && !rewind($stream)) {
            throw FileException::internal('无法读取上传内容');
        }
        $head = fread($stream, 8192);
        if ($head === false) {
            throw FileException::internal('无法识别文件类型');
        }
        if (!rewind($stream)) {
            throw FileException::internal('无法读取上传内容');
        }
        if ($head === '') {
            throw FileException::invalid(['file' => ['不能上传空文件']]);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($head);
        if (!is_string($mime) || $mime === '') {
            throw FileException::invalid(['file' => ['无法识别文件类型']]);
        }
        $separator = strpos($mime, ';');
        if ($separator !== false) {
            $mime = substr($mime, 0, $separator);
        }
        $mime = strtolower(trim($mime));
        if ($mime === '' || $mime === 'application/x-empty' || $mime === 'inode/x-empty') {
            throw FileException::invalid(['file' => ['不能上传空文件']]);
        }

        return $mime;
    }
}
