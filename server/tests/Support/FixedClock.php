<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Support;

use Sandadmin\Task\Clock;

final class FixedClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $now = '2026-11-10 00:00:00')
    {
        $this->now = new \DateTimeImmutable($now, new \DateTimeZone('UTC'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function move(\DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));
    }
}
