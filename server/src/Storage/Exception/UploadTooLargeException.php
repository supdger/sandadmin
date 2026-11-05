<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage\Exception;

final class UploadTooLargeException extends StorageException
{
    public function __construct(public readonly int $maxBytes)
    {
        parent::__construct(
            'upload_too_large',
            '上传文件超过大小上限',
            ['file' => '超过允许的大小（最大 ' . $maxBytes . ' 字节）'],
        );
    }
}
