<?php

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

return [
    // worker 轮询间隔（秒）
    'poll_interval' => 1.0,
    // 租约时长（秒）：执行期间靠心跳续租；过期未续租的 running 记录会被重新认领
    'lease_seconds' => 60,
    // 同一任务最多执行次数，超出后标记失败，不再重试
    'max_attempts' => 3,
    // 单次执行超时上限（秒），0 表示不限；timeouts 可按任务类型覆盖
    'timeout_seconds' => 300,
    'timeouts' => [],
    // 各类型同时运行的任务数上限，0 表示不限；concurrency 按类型覆盖
    'concurrency' => [],
    'default_concurrency' => 4,
    // 单次 tick 最多连续执行的任务数
    'max_batch' => 10,
];
