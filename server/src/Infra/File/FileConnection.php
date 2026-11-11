<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\File;

use Sandadmin\Domain\File\FileException;

/**
 * 文件模块的临时连接入口：从数据库配置数组构造 PDO。
 *
 * TODO(统一连接入口): §3.16.5 落地后本类应删除，webman 进程与 CLI 共用
 * 同一连接工厂；当前仅为过渡接缝，不引入第二套连接管理。
 */
final class FileConnection
{
    /**
     * @param array<array-key, mixed> $databaseConfig config('database.connections.pgsql') 形状
     */
    public static function pdo(array $databaseConfig): \PDO
    {
        $driver = $databaseConfig['driver'] ?? null;
        if ($driver !== 'pgsql' && $driver !== 'pdo_pgsql') {
            throw FileException::internal('文件模块只支持 PostgreSQL');
        }
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
            throw FileException::internal('数据库配置不完整');
        }

        return new \PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database),
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
    }
}
