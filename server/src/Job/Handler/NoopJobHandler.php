<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job\Handler;

use Sandadmin\Job\JobHandler;
use Sandadmin\Job\JobProgress;
use Sandadmin\Job\JobRun;

/**
 * Built-in handler used to prove a dispatched job can reach a terminal state.
 * It does not read the payload and keeps no process state.
 */
final class NoopJobHandler implements JobHandler
{
    public function handle(JobRun $job, JobProgress $progress): ?array
    {
        $progress->report('running', 50);

        return ['ok' => true];
    }
}
