<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * CLI 场景的临时连接入口：从数据库配置数组构造 PDO。
 *
 * TODO(B2): 统一数据库连接入口落地后，本类应删除，CLI 与 webman
 * 进程共用同一连接工厂；当前仅为过渡接缝，不引入第二套连接管理。
 */
final class TaskConnection
{
    /**
     * @param array<array-key, mixed> $databaseConfig config('database.connections.pgsql') 形状
     */
    public static function pdo(array $databaseConfig): \PDO
    {
        $host = $databaseConfig['host'] ?? '127.0.0.1';
        $port = $databaseConfig['port'] ?? 5432;
        $database = $databaseConfig['database'] ?? '';
        $username = $databaseConfig['username'] ?? '';
        $password = $databaseConfig['password'] ?? '';
        $host = is_string($host) && $host !== '' ? $host : '127.0.0.1';
        $port = is_int($port) ? $port : (is_string($port) && preg_match('/^\d+$/', $port) === 1 ? (int) $port : 5432);
        $database = is_string($database) ? $database : '';
        $username = is_string($username) ? $username : '';
        $password = is_string($password) ? $password : '';
        if ($database === '' || $username === '') {
            throw new TaskStorageException('数据库配置不完整');
        }
        $pdo = new \PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database),
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        return $pdo;
    }
}
