<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

/**
 * Composition root for the job CLI scripts. Reads the environment here so
 * library code does not call getenv() while handling a request.
 */
final class JobCli
{
    public static function boot(string $serverRoot): JobRuntime
    {
        $envFile = $serverRoot . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envFile)) {
            self::loadEnvFile($envFile);
        }
        $gss = $serverRoot . '/support/gssencmode.php';
        if (is_file($gss)) {
            require_once $gss;
        }
        $config = self::databaseConfig();
        $jobFile = $serverRoot . '/config/job.php';
        $job = require $jobFile;
        if (!is_array($job)) {
            throw new InvalidJobException('config/job.php 必须返回数组');
        }
        $runtime = JobRuntime::fromDatabaseConfig($config, $job, JobRuntime::workerId());
        $runtime->store->ensureSchema();

        return $runtime;
    }

    /**
     * @return array<string, mixed>
     */
    public static function databaseConfig(): array
    {
        $database = self::env('DB_NAME');
        if ($database === '') {
            throw new JobStorageException('缺少 DB_NAME。请在 server/.env 中配置数据库后再执行。');
        }
        $password = getenv('DB_PASSWORD');

        return [
            'default' => 'pgsql',
            'connections' => [
                'pgsql' => [
                    'driver' => self::env('DB_TYPE') === '' ? 'pgsql' : self::env('DB_TYPE'),
                    'host' => self::env('DB_HOST') === '' ? '127.0.0.1' : self::env('DB_HOST'),
                    'port' => self::env('DB_PORT') === '' ? '5432' : self::env('DB_PORT'),
                    'database' => $database,
                    'username' => self::env('DB_USER'),
                    'password' => $password === false ? '' : $password,
                ],
            ],
        ];
    }

    public static function loadEnvFile(string $path): void
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new JobStorageException('无法读取环境文件');
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            if ($key === '' || getenv($key) !== false) {
                continue;
            }
            $value = trim(substr($line, $eq + 1));
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = stripcslashes(substr($value, 1, -1));
            }
            putenv($key . '=' . $value);
        }
    }

    private static function env(string $key): string
    {
        $value = getenv($key);
        if ($value === false) {
            return '';
        }

        return $value;
    }
}
