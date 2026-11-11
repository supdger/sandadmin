<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 任务子系统的运行时装配：存储、投递、执行、处理器清单。
 * 数据库连接由调用方注入（webman 进程经共享连接入口，CLI 经
 * TaskConnection 解析 .env），本类不感知连接来源。
 */
final readonly class TaskRuntime
{
    public function __construct(
        public TaskStore $store,
        public TaskDispatcher $dispatcher,
        public TaskExecutor $executor,
        public TaskHandlerFactory $handlers,
        public TaskConfig $config,
    ) {
    }

    /**
     * webman 进程入口：从 config('database.connections.pgsql') 取连接参数。
     * 统一数据库连接入口（B1/B2）落地前，PDO 由 TaskConnection 这个
     * 可替换接缝构造；落地后只改这一处。
     */
    public static function fromWebman(): ?self
    {
        if (!function_exists('config')) {
            return null;
        }
        $database = config('database.connections.pgsql');
        if (!is_array($database)) {
            return null;
        }
        $taskConfig = config('task');

        try {
            return self::fromPdo(
                TaskConnection::pdo($database),
                is_array($taskConfig) ? $taskConfig : [],
                self::workerId(),
            );
        } catch (\Throwable $exception) {
            error_log('task 运行时初始化失败 ' . $exception::class . ': ' . $exception->getMessage());

            return null;
        }
    }

    private static function workerId(): string
    {
        $host = gethostname();

        return ($host === false ? 'localhost' : $host) . '-' . getmypid();
    }

    /**
     * @param array<array-key, mixed> $taskConfig config/task.php 原始数组
     */
    public static function fromPdo(\PDO $pdo, array $taskConfig, string $workerId): self
    {
        $config = TaskConfig::fromArray($taskConfig);
        $store = new PdoTaskStore($pdo);
        $clock = new SystemClock();
        $handlers = new MappedTaskHandlerFactory(TaskHandlerCatalog::host());

        return new self(
            store: $store,
            dispatcher: new TaskDispatcher($store, $clock),
            executor: new TaskExecutor($store, $handlers, $clock, $workerId, $config),
            handlers: $handlers,
            config: $config,
        );
    }
}
