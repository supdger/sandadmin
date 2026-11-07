<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class UnknownJobTypeException extends \RuntimeException
{
    public function __construct(string $type)
    {
        parent::__construct('未知任务类型: ' . $type);
    }
}
