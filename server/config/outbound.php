<?php

declare(strict_types=1);

/**
 * 统一出站 HTTP 客户端（src/Infra/Http）配置：三段超时毫秒分开、
 * 指数退避重试、响应体上限、SSRF 白名单（env 前缀 SAND_OUTBOUND_*）。
 */
return [
    // TCP 建连超时（毫秒）
    'connect_timeout_ms' => (int) env('SAND_OUTBOUND_CONNECT_TIMEOUT_MS', 3000),
    // 读停滞超时（毫秒）：任意方向低于 1B/s 持续该时长即中止
    'read_timeout_ms' => (int) env('SAND_OUTBOUND_READ_TIMEOUT_MS', 10000),
    // 单次尝试整体超时（毫秒），含建连与传输
    'total_timeout_ms' => (int) env('SAND_OUTBOUND_TOTAL_TIMEOUT_MS', 15000),
    // 最大尝试次数（含首次）；幂等方法的传输错误与 retry_statuses 会重试
    'max_attempts' => (int) env('SAND_OUTBOUND_MAX_ATTEMPTS', 3),
    // 重试退避基数（毫秒）：第 n 次重试延迟 base*2^(n-1) 封顶 backoff_max，再加 [0,base] 抖动
    'backoff_base_ms' => (int) env('SAND_OUTBOUND_BACKOFF_BASE_MS', 200),
    'backoff_max_ms' => (int) env('SAND_OUTBOUND_BACKOFF_MAX_MS', 2000),
    // 响应体上限（字节），超出立即中止并在 OutboundResponse.truncated 标记
    'max_body_bytes' => (int) env('SAND_OUTBOUND_MAX_BODY_BYTES', 10 * 1024 * 1024),
    // 触发重试的 HTTP 状态码
    'retry_statuses' => [502, 503, 504],
    // 出站代理：缺省不设置 CURLOPT_PROXY（沿用 curl 默认读环境变量 http_proxy 等）；
    // 设为代理地址走该代理，设为空串显式禁用代理（回环探测等场景用 ''）
    'proxy' => env('SAND_OUTBOUND_PROXY'),
    // SSRF 白名单：精确 host 或 *.后缀（大小写不敏感），逗号分隔；
    // 命中后该主机解析到的内网/保留地址才放行，公网主机不受影响
    'allow_private_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SAND_OUTBOUND_ALLOW_PRIVATE_HOSTS', '')),
    ), static fn (string $host): bool => $host !== '')),
];
