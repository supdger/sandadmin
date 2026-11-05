<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class JobRuntime
{
    public function __construct(
        public readonly JobStore $store,
        public readonly JobDispatcher $dispatcher,
        public readonly JobExecutor $executor,
    ) {
    }

    /**
     * @param array<string, mixed> $databaseConfig
     * @param array<string, mixed> $jobConfig
     */
    public static function fromDatabaseConfig(array $databaseConfig, array $jobConfig, string $workerId): self
    {
        $pdo = JobConnection::open($databaseConfig);
        $store = new PdoJobStore($pdo);
        $clock = new SystemClock();
        $handlers = $jobConfig['handlers'] ?? [];
        if (!is_array($handlers)) {
            throw new InvalidJobException('任务处理器配置不正确');
        }
        /** @var array<string, JobHandler> $typed */
        $typed = [];
        foreach ($handlers as $type => $handler) {
            if (!is_string($type) || !$handler instanceof JobHandler) {
                throw new InvalidJobException('任务处理器配置不正确');
            }
            $typed[$type] = $handler;
        }

        return new self(
            $store,
            new JobDispatcher($store, new UuidGenerator(), $clock),
            new JobExecutor($store, new MappedJobHandlerFactory($typed), $clock, $workerId),
        );
    }

    /**
     * Build a runtime from webman config. Returns null when the database name
     * is still empty (installer has not written .env).
     */
    public static function fromWebman(): ?self
    {
        if (!function_exists('config')) {
            throw new JobStorageException('JobRuntime::fromWebman 只能在 webman 进程内调用');
        }
        $database = config('database');
        if (!is_array($database)) {
            return null;
        }
        $default = $database['default'] ?? 'pgsql';
        if (!is_string($default)) {
            return null;
        }
        $connections = $database['connections'] ?? null;
        $databaseName = '';
        if (is_array($connections) && isset($connections[$default]) && is_array($connections[$default])) {
            $name = $connections[$default]['database'] ?? '';
            $databaseName = is_string($name) ? $name : '';
        }
        if ($databaseName === '') {
            return null;
        }
        $job = config('job');
        if (!is_array($job)) {
            $job = [];
        }

        return self::fromDatabaseConfig($database, $job, self::workerId());
    }

    public static function workerId(): string
    {
        $host = php_uname('n');
        $id = $host . ':' . (string) getmypid();
        if (strlen($id) > 128) {
            $id = substr($id, 0, 128);
        }

        return $id;
    }
}
