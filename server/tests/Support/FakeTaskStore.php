<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Support;

use Sandadmin\Task\TaskClaimPolicy;
use Sandadmin\Task\TaskIdempotencyConflictException;
use Sandadmin\Task\TaskPage;
use Sandadmin\Task\TaskRun;
use Sandadmin\Task\TaskState;
use Sandadmin\Task\TaskStorageException;
use Sandadmin\Task\TaskStore;

/**
 * 内存版 TaskStore：语义对齐 PdoTaskStore 的关键约束
 * （幂等唯一、租约认领、并发上限、终态条件更新），供单元测试使用。
 */
final class FakeTaskStore implements TaskStore
{
    /** @var array<string, TaskRun> */
    private array $tasks = [];

    /** @var array<string, int> publicId => 插入序号 */
    private array $sequence = [];

    private int $nextSequence = 0;

    public function insert(TaskRun $task): void
    {
        if (isset($this->tasks[$task->publicId])) {
            throw new TaskStorageException('任务编号冲突');
        }
        if ($task->idempotencyKey !== null) {
            foreach ($this->tasks as $existing) {
                if ($existing->type === $task->type && $existing->idempotencyKey === $task->idempotencyKey) {
                    throw new TaskIdempotencyConflictException('相同幂等键的任务已存在');
                }
            }
        }
        $this->tasks[$task->publicId] = $task;
        $this->sequence[$task->publicId] = $this->nextSequence++;
    }

    public function findByPublicId(string $publicId): ?TaskRun
    {
        return $this->tasks[$publicId] ?? null;
    }

    public function findByIdempotencyKey(string $type, string $idempotencyKey): ?TaskRun
    {
        foreach ($this->tasks as $task) {
            if ($task->type === $type && $task->idempotencyKey === $idempotencyKey) {
                return $task;
            }
        }

        return null;
    }

    public function claimNext(string $workerId, \DateTimeImmutable $now, TaskClaimPolicy $policy): ?TaskRun
    {
        $candidates = [];
        foreach ($this->tasks as $task) {
            $claimable = $task->state === TaskState::Queued
                || ($task->state === TaskState::Running
                    && $task->leaseExpireTime !== null
                    && $task->leaseExpireTime <= $now);
            if ($claimable) {
                $candidates[] = $task;
            }
        }
        usort($candidates, function (TaskRun $a, TaskRun $b): int {
            $time = $a->createTime <=> $b->createTime;
            if ($time !== 0) {
                return $time;
            }

            return ($this->sequence[$a->publicId] ?? 0) <=> ($this->sequence[$b->publicId] ?? 0);
        });

        $runningCounts = [];
        foreach ($this->tasks as $task) {
            if ($task->state === TaskState::Running) {
                $runningCounts[$task->type] = ($runningCounts[$task->type] ?? 0) + 1;
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate->attempt + 1 > $policy->maxAttempts) {
                $failed = new TaskRun(
                    publicId: $candidate->publicId,
                    type: $candidate->type,
                    state: TaskState::Failed,
                    stage: '执行失败',
                    progress: $candidate->progress,
                    result: $candidate->result,
                    errorCode: 'max_attempts_exceeded',
                    errorMessage: '任务多次执行未成功，已停止重试',
                    payload: $candidate->payload,
                    createdBy: $candidate->createdBy,
                    idempotencyKey: $candidate->idempotencyKey,
                    attempt: $candidate->attempt,
                    lockedBy: $candidate->lockedBy,
                    leaseExpireTime: null,
                    startTime: $candidate->startTime,
                    finishTime: $now,
                    createTime: $candidate->createTime,
                    updateTime: $now,
                );
                $this->tasks[$candidate->publicId] = $failed;
                continue;
            }
            $limit = $policy->limitFor($candidate->type);
            $running = $runningCounts[$candidate->type] ?? 0;
            if ($limit > 0 && $running >= $limit) {
                continue;
            }
            $leaseUntil = $now->modify(sprintf('+%d seconds', $policy->leaseSeconds));
            $claimed = new TaskRun(
                publicId: $candidate->publicId,
                type: $candidate->type,
                state: TaskState::Running,
                stage: '执行中',
                progress: $candidate->progress,
                result: $candidate->result,
                errorCode: $candidate->errorCode,
                errorMessage: $candidate->errorMessage,
                payload: $candidate->payload,
                createdBy: $candidate->createdBy,
                idempotencyKey: $candidate->idempotencyKey,
                attempt: $candidate->attempt + 1,
                lockedBy: $workerId,
                leaseExpireTime: $leaseUntil === false ? $now : $leaseUntil,
                startTime: $candidate->startTime ?? $now,
                finishTime: $candidate->finishTime,
                createTime: $candidate->createTime,
                updateTime: $now,
            );
            $this->tasks[$candidate->publicId] = $claimed;
            $runningCounts[$candidate->type] = $running + 1;

            return $claimed;
        }

        return null;
    }

    public function reportProgress(
        string $publicId,
        string $workerId,
        string $stage,
        int $progress,
        \DateTimeImmutable $now,
        int $leaseSeconds,
    ): void {
        $task = $this->requireRunning($publicId, $workerId);
        $leaseUntil = $now->modify(sprintf('+%d seconds', $leaseSeconds));
        $this->tasks[$publicId] = new TaskRun(
            publicId: $task->publicId,
            type: $task->type,
            state: $task->state,
            stage: $stage,
            progress: $progress,
            result: $task->result,
            errorCode: $task->errorCode,
            errorMessage: $task->errorMessage,
            payload: $task->payload,
            createdBy: $task->createdBy,
            idempotencyKey: $task->idempotencyKey,
            attempt: $task->attempt,
            lockedBy: $task->lockedBy,
            leaseExpireTime: $leaseUntil === false ? $now : $leaseUntil,
            startTime: $task->startTime,
            finishTime: $task->finishTime,
            createTime: $task->createTime,
            updateTime: $now,
        );
    }

    public function markSucceeded(string $publicId, string $workerId, ?array $result, \DateTimeImmutable $now): void
    {
        $task = $this->requireRunning($publicId, $workerId);
        $this->tasks[$publicId] = new TaskRun(
            publicId: $task->publicId,
            type: $task->type,
            state: TaskState::Succeeded,
            stage: '已完成',
            progress: 100,
            result: $result,
            errorCode: null,
            errorMessage: null,
            payload: $task->payload,
            createdBy: $task->createdBy,
            idempotencyKey: $task->idempotencyKey,
            attempt: $task->attempt,
            lockedBy: $task->lockedBy,
            leaseExpireTime: null,
            startTime: $task->startTime,
            finishTime: $now,
            createTime: $task->createTime,
            updateTime: $now,
        );
    }

    public function markFailed(
        string $publicId,
        string $workerId,
        string $errorCode,
        string $errorMessage,
        \DateTimeImmutable $now,
    ): void {
        $task = $this->requireRunning($publicId, $workerId);
        $this->tasks[$publicId] = new TaskRun(
            publicId: $task->publicId,
            type: $task->type,
            state: TaskState::Failed,
            stage: '执行失败',
            progress: $task->progress,
            result: $task->result,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            payload: $task->payload,
            createdBy: $task->createdBy,
            idempotencyKey: $task->idempotencyKey,
            attempt: $task->attempt,
            lockedBy: $task->lockedBy,
            leaseExpireTime: null,
            startTime: $task->startTime,
            finishTime: $now,
            createTime: $task->createTime,
            updateTime: $now,
        );
    }

    public function search(?int $createdBy, ?string $type, ?string $state, int $page, int $pageSize): TaskPage
    {
        $items = [];
        foreach ($this->tasks as $task) {
            if ($createdBy !== null && $task->createdBy !== $createdBy) {
                continue;
            }
            if ($type !== null && $type !== '' && $task->type !== $type) {
                continue;
            }
            if ($state !== null && $state !== '' && $task->state->value !== $state) {
                continue;
            }
            $items[] = $task;
        }
        usort($items, function (TaskRun $a, TaskRun $b): int {
            $time = $b->createTime <=> $a->createTime;
            if ($time !== 0) {
                return $time;
            }

            return ($this->sequence[$b->publicId] ?? 0) <=> ($this->sequence[$a->publicId] ?? 0);
        });
        $total = count($items);
        $items = array_slice($items, ($page - 1) * $pageSize, $pageSize);

        return new TaskPage($items, $total, $page, $pageSize);
    }

    private function requireRunning(string $publicId, string $workerId): TaskRun
    {
        $task = $this->tasks[$publicId] ?? null;
        if ($task === null || $task->state !== TaskState::Running || $task->lockedBy !== $workerId) {
            throw new TaskStorageException('任务执行租约已失效');
        }

        return $task;
    }
}
