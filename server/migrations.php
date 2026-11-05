<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

/**
 * Doctrine Migrations 路径。连接由未来的 bin/sand migrate 注入，本文件不读环境变量。
 *
 * @return array{
 *   migrations_paths: array<string, string>,
 *   table_storage: array{table_name: string},
 *   all_or_nothing: bool
 * }
 */
return [
    'migrations_paths' => [
        'Sandadmin\\Migrations' => __DIR__ . '/migrations',
    ],
    'table_storage' => [
        'table_name' => 'sand_migration_versions',
    ],
    'all_or_nothing' => true,
];
