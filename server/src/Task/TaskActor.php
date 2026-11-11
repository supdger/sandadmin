<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 一次请求里「谁在看任务」的身份。userId=0 只用于 CLI/系统上下文，
 * HTTP 请求的 actor 必然来自已登录用户（userId>=1）。
 */
final readonly class TaskActor
{
    public function __construct(
        public int $userId,
        /** 是否可看全部任务（持有 system:task:index 或等效权限） */
        public bool $viewAll,
    ) {
    }

    /**
     * 命令行等无登录态的操作上下文：可看全部，但不对应真实用户。
     */
    public static function system(): self
    {
        return new self(0, true);
    }
}
