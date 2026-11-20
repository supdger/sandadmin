<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Runtime;

use Workerman\Worker;

/**
 * 常驻进程软回收：web 组按 max_requests 计数，全部组按内存水位检查，
 * 触发后给本进程发 SIGUSR1——workerman 把它当 reload 处理，
 * master 监护发现子进程退出后自动拉起新进程（单进程平滑重启，
 * 在途请求随连接关闭冲刷发送缓冲，与 `webman reload` 同语义）。
 * 只在 Worker 处于 RUNNING 状态时发信号，测试与 CLI 进程天然不触发。
 */
final class ProcessRecycle
{
    /** 上限未配置时的兜底内存上限：256MiB（3k R11）。 */
    public const FALLBACK_LIMIT_BYTES = 268435456;

    private int $requests = 0;

    private bool $recycleRequested = false;

    public function __construct(
        private readonly int $maxRequests,
        private readonly int $memoryWatermarkBytes,
    ) {
        if ($maxRequests < 0 || $memoryWatermarkBytes < 0) {
            throw new \InvalidArgumentException('回收阈值为 0（不启用）或正数');
        }
    }

    /**
     * 从运行配置装配：max_requests 取 web 进程组 profile；
     * 水位优先 runtime.memory_watermark_bytes（0 显式关闭），
     * 缺省取进程内存上限的 80%，上限取 stream 各 profile
     * memory_limit_bytes 最大值，全部未配置时按 256MiB 兜底。
     */
    public static function fromConfig(): self
    {
        $explicit = self::read('runtime.memory_watermark_bytes');
        $watermark = is_int($explicit) && $explicit >= 0
            ? $explicit
            : self::deriveWatermark();

        return new self(
            maxRequests: self::nonNegativeInt(self::read('stream.profiles.web.max_requests'), 0),
            memoryWatermarkBytes: $watermark,
        );
    }

    /** 每处理完一个请求调用一次；到上限即回收。 */
    public function noteRequest(): void
    {
        if ($this->maxRequests <= 0) {
            return;
        }
        if (++$this->requests >= $this->maxRequests) {
            $this->requests = 0;
            $this->recycle('max_requests');
        }
    }

    /** 由定时器周期调用；真实内存占用到水位即回收。 */
    public function checkMemory(): void
    {
        if ($this->memoryWatermarkBytes <= 0) {
            return;
        }
        if (memory_get_usage(true) >= $this->memoryWatermarkBytes) {
            $this->recycle('memory_watermark');
        }
    }

    private static function deriveWatermark(): int
    {
        $cap = 0;
        $profiles = self::read('stream.profiles', []);
        if (is_array($profiles)) {
            foreach ($profiles as $profile) {
                if (is_array($profile)) {
                    $limit = $profile['memory_limit_bytes'] ?? 0;
                    if (is_int($limit) && $limit > $cap) {
                        $cap = $limit;
                    }
                }
            }
        }
        if ($cap <= 0) {
            $cap = self::FALLBACK_LIMIT_BYTES;
        }

        return intdiv($cap * 4, 5);
    }

    private static function read(string $key, mixed $default = null): mixed
    {
        return function_exists('config') ? config($key, $default) : $default;
    }

    private static function nonNegativeInt(mixed $value, int $default): int
    {
        return is_int($value) && $value >= 0 ? $value : $default;
    }

    private function recycle(string $reason): void
    {
        if ($this->recycleRequested || !self::canSignal()) {
            return;
        }
        $this->recycleRequested = true;
        Worker::log('worker recycle triggered: ' . $reason);
        posix_kill(posix_getpid(), SIGUSR1);
    }

    private static function canSignal(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && function_exists('posix_kill')
            && Worker::getStatus() === Worker::STATUS_RUNNING;
    }
}
