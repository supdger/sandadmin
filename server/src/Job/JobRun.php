<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final readonly class JobRun
{
    /**
     * @param array<string, mixed> $payload
     * @param array<mixed>|null $result
     */
    public function __construct(
        public string $id,
        public string $type,
        public JobStatus $status,
        public ?string $stage,
        public int $progress,
        public ?array $result,
        public ?string $error,
        public array $payload,
        public string $createdAt,
        public string $updatedAt,
        public ?string $startedAt,
        public ?string $finishedAt,
        public ?string $lockedBy,
    ) {
    }
}
