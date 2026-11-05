<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

use Sandadmin\Storage\StorageConfig;

return [
    'root' => runtime_path() . DIRECTORY_SEPARATOR . 'files',
    'max_bytes' => StorageConfig::DEFAULT_MAX_BYTES,
    'allowed_mime_types' => StorageConfig::defaultAllowedMimeTypes(),
];
