<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

use Sandadmin\Job\Handler\NoopJobHandler;

/**
 * Background job handlers. Instances must be stateless: progress, result and
 * errors are stored on job_run, not on the handler.
 */

return [
    'poll_interval' => 1.0,
    'handlers' => [
        'noop' => new NoopJobHandler(),
    ],
];
