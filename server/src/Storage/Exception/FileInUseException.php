<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage\Exception;

final class FileInUseException extends StorageException
{
    public function __construct()
    {
        parent::__construct('file_in_use', '文件仍被引用，不能删除', ['file_id' => '文件仍被引用']);
    }
}
