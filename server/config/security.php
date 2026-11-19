<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

return [
    // C-18：弱密码检查常开。forbid_weak 为 false 时命中表检查整体跳过，
    // 生产环境也不再要求词表文件存在。
    'forbid_weak' => true,
    // 常用弱密码命中表文件路径（随包 vendored）。改动后需重启 worker 生效。
    'weak_password_path' => env('SAND_WEAK_PASSWORD_PATH') ?: base_path() . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . 'weak-passwords.txt',

    // A-5：本站源，形如 https://admin.example.com（scheme://host[:port]，无尾斜杠）。
    // v1 组写操作要求 Origin 与之精确相等；只认本配置，不从 Host/转发头推导。
    // 留空 = 未启用源校验（自定义头仍必查），生产部署必须配置。
    'site_origin' => env('SAND_SITE_ORIGIN') ?: null,

    // N3 统一安全响应头（SecurityHeadersMiddleware 全局输出）。
    'headers' => [
        'referrer_policy' => env('SAND_REFERRER_POLICY') ?: 'strict-origin-when-cross-origin',
        // 原样透传 env：显式 '0' = 关闭 HSTS；空串/非整数由装载层落默认（不提前 (int) 吞错）
        'hsts_max_age' => env('SAND_HSTS_MAX_AGE'),
        // NET-01 两步上线：enforce=false 发 Content-Security-Policy-Report-Only 灰度。
        'csp' => [
            'enforce' => filter_var(env('SAND_CSP_ENFORCE', false), FILTER_VALIDATE_BOOLEAN),
            'api' => env('SAND_CSP_API') ?: "default-src 'none'",
            'page' => env('SAND_CSP_PAGE') ?: "default-src 'self'",
        ],
        'permissions_policy' => env('SAND_PERMISSIONS_POLICY') ?: 'camera=(), microphone=(), geolocation=()',
    ],

    // A-5 + 缺口检查 14：v1 写操作必带的自定义请求头（纯表单无法伪造）。
    // required_header_value 非空时还要求值相等；默认空 = 只要求存在。
    'csrf' => [
        'required_header_name' => env('SAND_CSRF_HEADER_NAME') ?: 'X-Requested-With',
        'required_header_value' => env('SAND_CSRF_HEADER_VALUE') ?: null,
    ],

    // v1 组跨域白名单：allowed_origins 留空 = 仅同源；精确匹配，不反射不通配。
    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (env('SAND_CORS_ALLOWED_ORIGINS') ?: '')),
        ), static fn (string $value): bool => $value !== '')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Idempotency-Key', 'X-Request-Id'],
        'max_age' => env('SAND_CORS_MAX_AGE'),
    ],
];
