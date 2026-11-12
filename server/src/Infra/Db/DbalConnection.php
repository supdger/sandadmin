<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * DBAL 连接过渡入口：与 TaskConnection/FileConnection 同理，
 * TODO(统一连接入口): B 系连接工厂落地后改为注入，不再各模块自建。
 */
final class DbalConnection
{
    /** @param array<array-key, mixed> $databaseConfig config('database.connections.pgsql') */
    public static function connection(array $databaseConfig): Connection
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
            throw new \RuntimeException('数据库配置不完整');
        }

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $host,
            'port' => $port,
            'dbname' => $database,
            'user' => $username,
            'password' => $password,
            'charset' => 'utf8',
        ]);
    }
}
