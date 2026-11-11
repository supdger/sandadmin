<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * task_run.state 流转状态，取值与 sand_system_task_run 的 CHECK 约束一致。
 */
enum TaskState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed || $this === self::Cancelled;
    }
}
