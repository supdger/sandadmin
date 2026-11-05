<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

interface JobStore
{
    public function ensureSchema(): void;

    public function insert(JobRun $job): void;

    public function find(string $id): ?JobRun;

    /**
     * Claim the oldest pending job. Returns null when none is available.
     * The claim is conditional on status still being pending.
     */
    public function claimNext(string $workerId, string $now): ?JobRun;

    public function updateProgress(string $id, string $stage, int $progress, string $now): void;

    /**
     * @param array<mixed>|null $result
     */
    public function markSucceeded(string $id, ?array $result, string $now): void;

    public function markFailed(string $id, string $error, string $now): void;
}
