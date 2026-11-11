<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 默认实现：恒返回 null，所有产物下载按 TASK_RUN_NOT_DOWNLOADABLE 处理。
 * 文件存储底座合入前不提供产物解析。
 */
final class UnavailableArtifactResolver implements TaskArtifactResolver
{
    public function resolve(TaskRun $task): ?TaskArtifact
    {
        return null;
    }
}
