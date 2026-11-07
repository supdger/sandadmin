<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class JobConnection
{
    /**
     * @param array<string, mixed> $databaseConfig webman config/database.php shape
     */
    public static function open(array $databaseConfig): \PDO
    {
        $default = $databaseConfig['default'] ?? 'pgsql';
        if (!is_string($default) || $default === '') {
            throw new JobStorageException('缺少默认数据库连接名');
        }
        $connections = $databaseConfig['connections'] ?? null;
        if (!is_array($connections) || !isset($connections[$default]) || !is_array($connections[$default])) {
            throw new JobStorageException('缺少数据库连接配置');
        }
        /** @var array<string, mixed> $conn */
        $conn = $connections[$default];
        $driver = $conn['driver'] ?? 'pgsql';
        if (!is_string($driver) || $driver === '') {
            throw new JobStorageException('缺少数据库驱动');
        }
        $database = $conn['database'] ?? '';
        if (!is_string($database) || ($database === '' && $driver !== 'sqlite')) {
            throw new JobStorageException('缺少数据库名称');
        }
        $username = $conn['username'] ?? '';
        $password = $conn['password'] ?? '';
        if (!is_string($username) || !is_string($password)) {
            throw new JobStorageException('数据库凭据格式不正确');
        }

        $dsn = self::dsn($driver, $conn, $database);
        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        if ($driver === 'pgsql') {
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        }
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA busy_timeout = 3000');
        }

        return $pdo;
    }

    /**
     * @param array<string, mixed> $conn
     */
    private static function dsn(string $driver, array $conn, string $database): string
    {
        if ($driver === 'sqlite') {
            if ($database === ':memory:') {
                return 'sqlite::memory:';
            }

            return 'sqlite:' . $database;
        }
        if ($driver !== 'pgsql') {
            throw new JobStorageException('job_run 仅支持 pgsql 与 sqlite');
        }
        $host = $conn['host'] ?? '127.0.0.1';
        $port = $conn['port'] ?? 5432;
        if (!is_string($host) && !is_int($host)) {
            throw new JobStorageException('数据库主机不正确');
        }
        if (!is_string($port) && !is_int($port)) {
            throw new JobStorageException('数据库端口不正确');
        }

        return 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database;
    }
}
