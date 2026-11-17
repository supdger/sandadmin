<?php

declare(strict_types=1);

/**
 * MCP 适配层配置。当前只有开发构建端点（dev）：.sand-dev-build 标记 +
 * SAND_MCP_DEV_TOKEN 同时成立才挂出；生产制品里整段不生效。
 * 限流是固定窗口：每凭据一个总桶、每个工具一个桶。
 */
return [
    'dev' => [
        // 每凭据每分钟允许进入协议层的请求数（鉴权之后的总闸）
        'credential_limit' => 60,
        // 每凭据每工具每分钟的调用上限
        'tool_limit' => 30,
        // 限流窗口（秒）
        'window_seconds' => 60,
        // dev_run_phpunit 子进程超时（秒）
        'test_timeout' => 120,
        // dev_run_pre_merge 子进程超时（秒）
        'gate_timeout' => 180,
    ],
];
