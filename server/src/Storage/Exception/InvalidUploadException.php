<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage\Exception;

final class InvalidUploadException extends StorageException
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(array $fields, string $message = '上传内容无效')
    {
        parent::__construct('invalid_upload', $message, $fields);
    }
}
