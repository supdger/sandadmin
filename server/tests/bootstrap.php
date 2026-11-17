<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

require __DIR__ . '/../vendor/autoload.php';

// 与 support/bootstrap.php 同一判定：标记文件在 ⇒ 本检出的 dev 链可用。
// 进程隔离的子进程设 SAND_TEST_DEV_BUILD=0 模拟无标记的生产制品。
if (!defined('SAND_DEV_BUILD')) {
    define('SAND_DEV_BUILD', getenv('SAND_TEST_DEV_BUILD') !== '0' && is_file(__DIR__ . '/../.sand-dev-build'));
}
