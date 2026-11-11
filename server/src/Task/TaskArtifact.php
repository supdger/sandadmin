<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 任务产物文件的本机定位。path 只在服务端内部使用，不对外暴露存储布局。
 */
final readonly class TaskArtifact
{
    public function __construct(
        public int $fileId,
        public string $name,
        public string $mediaType,
        public int $sizeBytes,
        public string $path,
    ) {
    }
}
