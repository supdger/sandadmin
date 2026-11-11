<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

interface TaskStore
{
    public function insert(TaskRun $task): void;

    public function findByPublicId(string $publicId): ?TaskRun;

    public function findByIdempotencyKey(string $type, string $idempotencyKey): ?TaskRun;

    /**
     * 认领一个可执行任务：queued，或 running 但租约已过期（执行者失联后回收）。
     * 领取即 attempt+1 并写入租约；超过 maxAttempts 的记录直接判失败。
     */
    public function claimNext(string $workerId, \DateTimeImmutable $now, TaskClaimPolicy $policy): ?TaskRun;

    /**
     * 执行中心跳：刷新阶段/进度并续租。租约已易主或任务已结束时报错。
     */
    public function reportProgress(string $publicId, string $workerId, string $stage, int $progress, \DateTimeImmutable $now, int $leaseSeconds): void;

    /**
     * @param array<string, mixed>|null $result
     */
    public function markSucceeded(string $publicId, string $workerId, ?array $result, \DateTimeImmutable $now): void;

    public function markFailed(string $publicId, string $workerId, string $errorCode, string $errorMessage, \DateTimeImmutable $now): void;

    public function search(?int $createdBy, ?string $type, ?string $state, int $page, int $pageSize): TaskPage;
}
