<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

interface TaskHandler
{
    /**
     * @return array<string, mixed>|null JSON 可编码的结果摘要，写入 result 列
     */
    public function handle(TaskRun $task, TaskProgress $progress): ?array;
}
