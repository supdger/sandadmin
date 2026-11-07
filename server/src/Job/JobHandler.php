<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

interface JobHandler
{
    /**
     * @return array<mixed>|null JSON-encodable result stored on the job row
     */
    public function handle(JobRun $job, JobProgress $progress): ?array;
}
