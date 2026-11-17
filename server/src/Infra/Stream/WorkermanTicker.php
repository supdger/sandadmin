<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\StreamTicker;
use Workerman\Timer;

/**
 * Workerman\Timer 的 StreamTicker 实现。Timer 只在 workerman 运行期
 * 可用——非 worker 上下文调用会抛 RuntimeException（同仓 TaskWorker
 * 与 LogRetentionWorker 的口径）；测试用 tests/Support 的手动拨表实现。
 */
final class WorkermanTicker implements StreamTicker
{
    public function now(): float
    {
        return microtime(true);
    }

    public function every(float $seconds, \Closure $tick): int
    {
        return Timer::add($seconds, $tick);
    }

    public function after(float $seconds, \Closure $tick): int
    {
        return Timer::add($seconds, $tick, [], false);
    }

    public function cancel(int $timerId): void
    {
        Timer::del($timerId);
    }
}
