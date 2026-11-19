<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

return [
    // 本部署分到的节点号段（闭区间，0-8191），格式 "起始-结束"。
    // 由部署按分段方案下发；同一号段内不同发号进程必须各拿不同的节点号。
    'node_range' => env('SAND_IDGEN_NODE_RANGE') ?: '',
    // 本机发号节点号，必须落在 node_range 内，否则启动自检拒绝启动。
    'node_id' => env('SAND_IDGEN_NODE_ID') ?: '',
    // 时钟回拨拒发阈值（毫秒）：回拨小于阈值等待时钟追上，达到阈值拒发并告警。
    // 默认值待技术总监定（v4 J-3 待决项），定稿前必填，缺失或非正数拒绝启动。
    'max_clock_backwards_ms' => env('SAND_IDGEN_MAX_CLOCK_BACKWARDS_MS') ?: '',
];
