<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\File;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Sandadmin\Application\File\BoundedCopier;
use Sandadmin\Application\File\FileUsage;
use Sandadmin\Application\File\UploadService;
use Sandadmin\Infra\Storage\DbalFileUsageRegistry;
use Sandadmin\Infra\Storage\FinfoMimeSniffer;
use Sandadmin\Infra\Storage\LocalFlysystemStore;
use Sandadmin\Storage\Exception\StorageException;
use Sandadmin\Storage\StorageConfig;

/** 框架入口的组装点。连接只在当次调用内存活，不放进进程级变量。 */
final class FileStackFactory
{
    public static function uploadService(StorageConfig $config): UploadService
    {
        return new UploadService(
            LocalFlysystemStore::open($config->root),
            $config,
            new FinfoMimeSniffer(),
            new BoundedCopier(),
        );
    }

    public static function usageHandle(StorageConfig $config, Connection $connection): FileUsageHandle
    {
        $usage = new FileUsage(
            LocalFlysystemStore::open($config->root),
            new DbalFileUsageRegistry($connection),
        );

        return new FileUsageHandle(new FileUsageHttp($usage, new HttpErrorMapper()), $connection);
    }

    /**
     * @param array<string, mixed> $database
     */
    public static function connectionFromDatabaseConfig(array $database): Connection
    {
        $name = $database['database'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new StorageException('storage_unavailable', '数据库尚未配置');
        }
        $driver = $database['driver'] ?? null;
        if ($driver !== 'pgsql' && $driver !== 'pdo_pgsql') {
            throw new StorageException('storage_unavailable', '文件登记只支持 PostgreSQL');
        }
        $host = $database['host'] ?? '127.0.0.1';
        $user = $database['username'] ?? '';
        $password = $database['password'] ?? '';
        $port = $database['port'] ?? 5432;
        $portOk = is_int($port) || (is_string($port) && ctype_digit($port));
        if (!is_string($host) || $host === '' || !is_string($user) || !is_string($password) || !$portOk) {
            throw new StorageException('storage_unavailable', '数据库尚未配置');
        }

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $host,
            'port' => (int) $port,
            'dbname' => $name,
            'user' => $user,
            'password' => $password,
        ]);
    }
}
