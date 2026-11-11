<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 处理器上报进度的入口：每次 report 同时刷新阶段、进度并续租（心跳），
 * 租约到期仍未上报的 running 记录会被其他 worker 回收重领。
 */
final class TaskProgress
{
    public function __construct(
        private readonly TaskStore $store,
        private readonly Clock $clock,
        private readonly string $publicId,
        private readonly string $workerId,
        private readonly int $leaseSeconds,
        private readonly ?\DateTimeImmutable $deadline,
    ) {
    }

    public function report(string $stage, int $progress): void
    {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidTaskException('任务进度必须在 0 到 100 之间');
        }
        // stage 列为 varchar(32)，人话阶段描述
        if (preg_match('/^[\p{L}\p{N}_. \-—]{1,32}$/u', $stage) !== 1) {
            throw new InvalidTaskException('任务阶段描述不正确');
        }
        $now = $this->clock->now();
        if ($this->deadline !== null && $now >= $this->deadline) {
            throw new TaskTimeoutException('任务执行超时');
        }
        $leaseUntil = $now->modify(sprintf('+%d seconds', $this->leaseSeconds));
        if ($leaseUntil === false) {
            throw new TaskStorageException('无法计算任务租约');
        }
        $this->store->reportProgress($this->publicId, $this->workerId, $stage, $progress, $now, $this->leaseSeconds);
    }
}
