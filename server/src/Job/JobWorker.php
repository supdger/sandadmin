<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

/**
 * Webman custom process. Registered from config/process.php.
 * Job progress is written to job_run. This process does not keep request data.
 */
final class JobWorker
{
    private bool $schemaReady = false;

    public function __construct(private readonly float $pollInterval = 1.0)
    {
    }

    public function onWorkerStart(object $worker): void
    {
        if (!class_exists(\Workerman\Timer::class)) {
            throw new JobStorageException('JobWorker 需要 Workerman\\Timer，请在 webman 进程内启动');
        }
        $interval = $this->resolveInterval();
        \Workerman\Timer::add($interval, function (): void {
            $this->tick();
        });
    }

    private function tick(): void
    {
        try {
            $runtime = JobRuntime::fromWebman();
            if ($runtime === null) {
                return;
            }
            if (!$this->schemaReady) {
                $runtime->store->ensureSchema();
                $this->schemaReady = true;
            }
            $runtime->executor->tick();
        } catch (\Throwable $e) {
            $this->report($e);
        }
    }

    private function resolveInterval(): float
    {
        $interval = $this->pollInterval;
        if (function_exists('config')) {
            $job = config('job');
            if (is_array($job) && isset($job['poll_interval']) && is_numeric($job['poll_interval'])) {
                $interval = (float) $job['poll_interval'];
            }
        }
        if ($interval <= 0.0 || $interval > 60.0) {
            return 1.0;
        }

        return $interval;
    }

    private function report(\Throwable $e): void
    {
        $message = 'job worker: ' . $e->getMessage();
        if (class_exists(\support\Log::class)) {
            \support\Log::channel('default')->error($message);

            return;
        }
        throw $e;
    }
}
