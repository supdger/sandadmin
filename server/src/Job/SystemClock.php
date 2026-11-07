<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class SystemClock implements Clock
{
    public function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
