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
];
