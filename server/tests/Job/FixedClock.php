<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Job;

use Sandadmin\Job\Clock;

final class FixedClock implements Clock
{
    public function __construct(private readonly string $now = '2026-11-05 00:00:00')
    {
    }

    public function now(): string
    {
        return $this->now;
    }
}
