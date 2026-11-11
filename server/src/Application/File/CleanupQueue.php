<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\File;

/** POST /files/cleanup 的受理端口：向 sand_system_task_run 投递清理任务。 */
interface CleanupQueue
{
    /**
     * @return array{task_id: string, state: string} TaskAccepted 形状；
     *         task_id 为 sand_system_task_run.public_id（uuid）
     */
    public function enqueue(int $createdBy, ?string $idempotencyKey): array;
}
