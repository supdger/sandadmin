<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task\Handler;

use Sandadmin\Task\TaskHandler;
use Sandadmin\Task\TaskProgress;
use Sandadmin\Task\TaskRun;

/**
 * 内置处理器：验证「投递 → 认领 → 执行 → 回写」链路可到达终态。
 * 不读载荷，不保留进程内状态。
 */
final class NoopTaskHandler implements TaskHandler
{
    /**
     * @return array<string, mixed>
     */
    public function handle(TaskRun $task, TaskProgress $progress): array
    {
        $progress->report('执行中', 50);

        return ['ok' => true];
    }
}
