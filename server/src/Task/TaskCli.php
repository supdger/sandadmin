<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * CLI 入口装配：加载 .env 与 config/task.php，构造 TaskRuntime。
 * 不创建表结构——sand_system_task_run 由基线迁移负责。
 */
final class TaskCli
{
    /**
     * @return array{runtime: TaskRuntime}|array{error: string}
     */
    public static function boot(string $basePath): array
    {
        self::loadEnvFile($basePath . DIRECTORY_SEPARATOR . '.env');
        if (is_file($basePath . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'gssencmode.php')) {
            require_once $basePath . DIRECTORY_SEPARATOR . 'support' . DIRECTORY_SEPARATOR . 'gssencmode.php';
        }
        $databaseConfig = self::databaseConfig();
        $taskConfig = self::taskConfig($basePath);
        if ($databaseConfig === null || $taskConfig === null) {
            return ['error' => '配置加载失败'];
        }

        try {
            return [
                'runtime' => TaskRuntime::fromPdo(
                    TaskConnection::pdo($databaseConfig),
                    $taskConfig,
                    self::workerId(),
                ),
            ];
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function databaseConfig(): ?array
    {
        // config/database.php 依赖 env() 与 base_path()，CLI 下直接解析 .env 后
        // 手工组装等价连接参数，避免引入 webman 引导。
        $env = self::envMap();
        $database = $env['DB_NAME'] ?? '';
        $username = $env['DB_USER'] ?? '';
        if ($database === '' || $username === '') {
            return null;
        }

        return [
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => (int) ($env['DB_PORT'] ?? 5432),
            'database' => $database,
            'username' => $username,
            'password' => $env['DB_PASSWORD'] ?? '',
        ];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function taskConfig(string $basePath): ?array
    {
        $file = $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'task.php';
        if (!is_file($file)) {
            return [];
        }
        $config = require $file;

        return is_array($config) ? $config : null;
    }

    /**
     * @return array<string, string>
     */
    private static function envMap(): array
    {
        $map = [];
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_TYPE'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    private static function loadEnvFile(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    private static function workerId(): string
    {
        $host = gethostname();

        return 'cli-' . ($host === false ? 'localhost' : $host) . '-' . getmypid();
    }
}
