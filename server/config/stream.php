<?php

declare(strict_types=1);

/**
 * 流式基础层进程组配置（J-5）：每进程挂路数上限初值 50–100 按压测定，
 * 心跳 15 秒、权限复查 30 秒、单流总时长硬顶 300 秒为设计定值。
 * max_requests / memory_limit_bytes 是进程回收阈值，0 表示不按该项回收。
 */
return [
    'profiles' => [
        // 普通 Web 请求进程组（MCP 请求也进这一组，U-3/M-03）。
        // 两项留环境变量口子：压测要单进程挂 100 路与 30 秒慢上游窗口，
        // 生产不写环境变量即取设计定值。
        'web' => [
            'stream_limit' => max(1, (int) env('SANDADMIN_STREAM_LIMIT', 80)),
            'idle_seconds' => max(1, (int) env('SANDADMIN_STREAM_IDLE_SECONDS', 30)),
            'stream_seconds' => 300,
            'heartbeat_seconds' => 15,
            'probe_seconds' => 30,
            'backpressure' => 'pause',
            'max_requests' => 0,
            'memory_limit_bytes' => 0,
        ],
        // AI 流式/长连接通道进程组（ai_stream_workers，300 路 ÷ 100 ≈ 3–4 进程）
        'ai_stream' => [
            'stream_limit' => 100,
            'idle_seconds' => 30,
            'stream_seconds' => 300,
            'heartbeat_seconds' => 15,
            'probe_seconds' => 30,
            'backpressure' => 'pause',
            'max_requests' => 0,
            'memory_limit_bytes' => 0,
        ],
    ],
];
