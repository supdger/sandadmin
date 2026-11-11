<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * sand_system_task_run 行的只读视图。public_id 是对外任务编号（task_id），
 * 内部自增 id 不进入本对象。
 */
final readonly class TaskRun
{
    /**
     * @param array<string, mixed> $payload 入参；不得含口令、密钥
     * @param array<string, mixed>|null $result 结果摘要；不得含敏感数据
     */
    public function __construct(
        public string $publicId,
        public string $type,
        public TaskState $state,
        public ?string $stage,
        public int $progress,
        public ?array $result,
        public ?string $errorCode,
        public ?string $errorMessage,
        public array $payload,
        public int $createdBy,
        public ?string $idempotencyKey,
        public int $attempt,
        public ?string $lockedBy,
        public ?\DateTimeImmutable $leaseExpireTime,
        public ?\DateTimeImmutable $startTime,
        public ?\DateTimeImmutable $finishTime,
        public \DateTimeImmutable $createTime,
        public \DateTimeImmutable $updateTime,
    ) {
    }
}
