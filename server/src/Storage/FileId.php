<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage;

use Sandadmin\Storage\Exception\InvalidUsageException;

final class FileId
{
    public const PATTERN = '/\A[a-f0-9]{32}\z/';

    public static function assert(string $id): string
    {
        if (preg_match(self::PATTERN, $id) !== 1) {
            throw new InvalidUsageException(['file_id' => '文件标识无效']);
        }

        return $id;
    }
}
