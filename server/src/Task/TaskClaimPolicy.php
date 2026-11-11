<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 认领策略：租约时长、最大尝试次数、按类型并发上限。
 */
final readonly class TaskClaimPolicy
{
    /**
     * @param array<string, int> $typeLimits 各类型同时运行的任务数上限；0 表示不限
     */
    public function __construct(
        public int $leaseSeconds,
        public int $maxAttempts,
        public array $typeLimits,
        public int $defaultLimit,
    ) {
    }

    public function limitFor(string $type): int
    {
        return $this->typeLimits[$type] ?? $this->defaultLimit;
    }
}
