<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

use Sandadmin\Application\File\StorageConfig;

return [
    // 存储根在 web 根之外（webman 的 public/ 才是 web 根），且对象键是随机串、不可被猜到路径
    'root' => runtime_path() . DIRECTORY_SEPARATOR . 'files',
    'max_bytes' => StorageConfig::DEFAULT_MAX_BYTES,
    'allowed_mime_types' => StorageConfig::defaultAllowedMimeTypes(),
    'allowed_extensions' => StorageConfig::defaultAllowedExtensions(),
];
