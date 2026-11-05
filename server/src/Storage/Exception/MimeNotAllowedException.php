<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage\Exception;

use Sandadmin\Storage\StorageConfig;

final class MimeNotAllowedException extends StorageException
{
    public function __construct(public readonly string $mime)
    {
        $detail = preg_match(StorageConfig::MIME_PATTERN, $mime) === 1 ? $mime : '不允许该文件类型';
        parent::__construct('mime_not_allowed', '不允许上传该类型的文件', ['file' => $detail]);
    }
}
