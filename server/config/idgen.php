<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

return [
    // 本部署分到的节点号段（闭区间，0-8191），格式 "起始-结束"。
    // 由部署按分段方案下发，不同部署的号段不得重叠；号段宽度至少容纳
    // 本机全部发号进程（web/task_worker/monitor/log_retention/插件进程）。
    'node_range' => env('SAND_IDGEN_NODE_RANGE') ?: '',
    // 本机节点基值：各发号进程实际节点号为「基值 + 进程槽位」（见 SnowflakeNodePlan），
    // 基值加全部发号进程数减一必须仍在号段内，否则启动自检拒绝启动。
    'node_id' => env('SAND_IDGEN_NODE_ID') ?: '',
    // 时钟回拨拒发阈值（毫秒）：回拨小于阈值等待时钟追上，达到阈值拒发并告警。
    // 推荐值 10（v4 J-3 已定）：NTP 校时抖动在毫秒级，10ms 内等待即可追上，
    // 更大回拨视为时钟异常拒发。必填，缺失或非正数拒绝启动。
    'max_clock_backwards_ms' => env('SAND_IDGEN_MAX_CLOCK_BACKWARDS_MS') ?: '',
];
