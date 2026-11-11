<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 由任务结果（result.file_id）解析产物文件的接缝。文件存储底座（B6）
 * 合入后由读取 sand_system_file 与本机存储的实现替换默认实现。
 */
interface TaskArtifactResolver
{
    /**
     * 返回可下载产物；任务未成功、产物已清理或无产物时返回 null。
     */
    public function resolve(TaskRun $task): ?TaskArtifact;
}
