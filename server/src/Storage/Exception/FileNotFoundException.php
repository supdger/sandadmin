<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage\Exception;

final class FileNotFoundException extends StorageException
{
    public function __construct()
    {
        parent::__construct('file_not_found', '文件不存在', ['file_id' => '文件不存在']);
    }
}
