<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Application\File\CleanupQueue;

/** 内存版清理队列：记录最近一次入队参数，返回固定 TaskAccepted 形状。 */
final class FakeCleanupQueue implements CleanupQueue
{
    public const TASK_ID = '11111111-2222-4333-8444-555555555555';

    public ?int $createdBy = null;

    public ?string $idempotencyKey = null;

    public int $calls = 0;

    public function enqueue(int $createdBy, ?string $idempotencyKey): array
    {
        $this->calls++;
        $this->createdBy = $createdBy;
        $this->idempotencyKey = $idempotencyKey;

        return ['task_id' => self::TASK_ID, 'state' => 'queued'];
    }
}
