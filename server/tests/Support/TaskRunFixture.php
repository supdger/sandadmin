<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Support;

use Sandadmin\Task\TaskRun;
use Sandadmin\Task\TaskState;

/**
 * TaskRun 测试夹具：只暴露常用可变字段，其余给契约合法默认值。
 */
final class TaskRunFixture
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $result
     */
    public static function make(
        ?string $publicId = null,
        string $type = 'noop',
        TaskState $state = TaskState::Queued,
        int $createdBy = 7,
        ?string $idempotencyKey = null,
        int $attempt = 0,
        ?string $lockedBy = null,
        ?\DateTimeImmutable $leaseExpireTime = null,
        ?string $stage = null,
        int $progress = 0,
        ?array $result = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?\DateTimeImmutable $startTime = null,
        ?\DateTimeImmutable $finishTime = null,
        ?\DateTimeImmutable $createTime = null,
        array $payload = [],
    ): TaskRun {
        $now = $createTime ?? new \DateTimeImmutable('2026-11-10 00:00:00', new \DateTimeZone('UTC'));

        return new TaskRun(
            publicId: $publicId ?? self::uuid(),
            type: $type,
            state: $state,
            stage: $stage,
            progress: $progress,
            result: $result,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            payload: $payload,
            createdBy: $createdBy,
            idempotencyKey: $idempotencyKey,
            attempt: $attempt,
            lockedBy: $lockedBy,
            leaseExpireTime: $leaseExpireTime,
            startTime: $startTime,
            finishTime: $finishTime,
            createTime: $now,
            updateTime: $now,
        );
    }

    public static function uuid(): string
    {
        return sprintf(
            '%08x-%04x-4%03x-8%03x-%012x',
            random_int(0, 0xffffffff),
            random_int(0, 0xffff),
            random_int(0, 0xfff),
            random_int(0, 0xfff),
            random_int(0, 0xffffffffffff)
        );
    }
}
