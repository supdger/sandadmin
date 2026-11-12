<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 任务 worker：webman 自定义进程入口，周期性认领并执行任务。
 * 不创建表结构——sand_system_task_run 由基线迁移负责。
 */
final class TaskWorker
{
    private ?TaskRuntime $runtime = null;

    public function onWorkerStart(object $worker): void
    {
        if (!class_exists(\Workerman\Timer::class)) {
            throw new TaskStorageException('TaskWorker 需要 Workerman\\Timer，请在 webman 进程内启动');
        }
        $config = $this->config();
        \Workerman\Timer::add(
            (float) $config->pollInterval,
            function () use ($config): void {
                $this->tick($config->maxBatch);
            }
        );
    }

    private function tick(int $maxBatch): void
    {
        try {
            $this->runtime ??= TaskRuntime::fromWebman();
            if ($this->runtime === null) {
                return;
            }
            for ($index = 0; $index < $maxBatch; $index++) {
                if ($this->runtime->executor->tick() === null) {
                    break;
                }
            }
        } catch (\Throwable $exception) {
            // 连接可能已断开：丢弃运行时，下个周期重建
            $this->runtime = null;
            error_log('task_worker tick 异常 ' . $exception::class . ': ' . $exception->getMessage());
        }
    }

    private function config(): TaskConfig
    {
        $raw = function_exists('config') ? config('task') : null;

        return TaskConfig::fromArray(is_array($raw) ? $raw : []);
    }
}
