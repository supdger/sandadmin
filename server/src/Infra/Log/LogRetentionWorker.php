<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

use RuntimeException;
use support\Log;
use Throwable;
use Workerman\Timer;

/**
 * 日志留存清理进程：webman 自定义进程，启动时清一次、之后每 24 小时清理
 * 过期日志文件。清理目标与天数全部来自 LogRetentionPolicy（config('log')
 * 各通道 retention），将来的库表日志清理也复用同一策略入口。
 */
final class LogRetentionWorker
{
    private const INTERVAL_SECONDS = 86400;

    public function onWorkerStart(object $worker): void
    {
        if (!class_exists(Timer::class)) {
            throw new RuntimeException('LogRetentionWorker 需要 Workerman\\Timer，请在 webman 进程内启动');
        }
        $this->sweep();
        Timer::add(self::INTERVAL_SECONDS, function (): void {
            $this->sweep();
        });
    }

    private function sweep(): void
    {
        try {
            $config = config('log', []);
            $policy = LogRetentionPolicy::fromArray(is_array($config) ? $config : []);
            foreach ((new LogFileCleaner())->clean($policy) as $row) {
                if ($row['deleted_count'] === 0 && $row['skipped_count'] === 0) {
                    continue;
                }
                Log::channel('default')->info('log retention sweep', [
                    'channel' => $row['channel'],
                    'days' => $row['days'],
                    'deleted_count' => $row['deleted_count'],
                    'skipped_count' => $row['skipped_count'],
                ]);
            }
        } catch (Throwable $error) {
            error_log('log_retention sweep 异常 ' . $error::class . ': ' . $error->getMessage());
        }
    }
}
