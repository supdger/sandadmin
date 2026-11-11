<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

/**
 * 生命周期详情可见性：申请人本人，或持有
 * system:plugin:index / system:update:index 等效权限的管理员可看全部。
 */
final readonly class LifecycleActor
{
    public function __construct(
        public int $userId,
        public bool $viewAll,
    ) {
    }
}
