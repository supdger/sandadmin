<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Job;

use Sandadmin\Job\IdGenerator;

final class SequenceIdGenerator implements IdGenerator
{
    private int $n = 0;

    public function next(): string
    {
        $this->n++;
        $tail = str_pad((string) $this->n, 12, '0', STR_PAD_LEFT);

        return '00000000-0000-4000-8000-' . $tail;
    }
}
