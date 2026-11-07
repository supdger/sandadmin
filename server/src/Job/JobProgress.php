<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class JobProgress
{
    public function __construct(
        private readonly JobStore $store,
        private readonly Clock $clock,
        private readonly string $jobId,
    ) {
    }

    public function report(string $stage, int $progress): void
    {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidJobException('进度必须在 0 到 100 之间');
        }
        if (preg_match('/^[\p{L}\p{N}_. -]{1,64}$/u', $stage) !== 1) {
            throw new InvalidJobException('阶段名称不正确');
        }
        $this->store->updateProgress($this->jobId, $stage, $progress, $this->clock->now());
    }
}
