<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class MissingJobSchemaException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('job_run 表不存在，请先执行 php bin/job-migrate.php');
    }
}
