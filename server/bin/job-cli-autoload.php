<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 *
 * Loads Sandadmin\* without requiring Composer when vendor/ is absent.
 * Prefer vendor/autoload.php when it exists so the same map as the host is used.
 */

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Sandadmin\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});
