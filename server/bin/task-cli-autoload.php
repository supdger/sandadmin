<?php
/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

// CLI 脚本统一入口：把 server/ 加入 include path 后加载 Composer 自动加载。
$serverRoot = dirname(__DIR__);
$autoload = $serverRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "缺少 vendor/autoload.php，请先在 server/ 执行 composer install\n");
    exit(1);
}
require $autoload;
