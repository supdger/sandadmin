<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Domain\Stream\StreamTicker;

/**
 * 手动拨表实现 StreamTicker：单测不依赖 Workerman 事件循环。
 * advance() 把虚拟钟往前推，到点定时器按触发时刻先后执行；
 * 周期定时器在执行回调前先排下一次，回调里 close() 撤表也不影响本次派发。
 */
final class ManualTicker implements StreamTicker
{
    private float $now;

    private int $nextId = 1;

    /** @var array<int, array{interval: float, next: float, persistent: bool, tick: \Closure(): void}> */
    private array $timers = [];

    public function __construct(float $now = 1_700_000_000.0)
    {
        $this->now = $now;
    }

    public function now(): float
    {
        return $this->now;
    }

    public function every(float $seconds, \Closure $tick): int
    {
        $id = $this->nextId++;
        $this->timers[$id] = [
            'interval' => $seconds,
            'next' => $this->now + $seconds,
            'persistent' => true,
            'tick' => $tick,
        ];

        return $id;
    }

    public function after(float $seconds, \Closure $tick): int
    {
        $id = $this->nextId++;
        $this->timers[$id] = [
            'interval' => $seconds,
            'next' => $this->now + $seconds,
            'persistent' => false,
            'tick' => $tick,
        ];

        return $id;
    }

    public function cancel(int $timerId): void
    {
        unset($this->timers[$timerId]);
    }

    public function advance(float $seconds): void
    {
        $target = $this->now + $seconds;
        while (true) {
            $earliestId = null;
            $earliestAt = $target;
            foreach ($this->timers as $id => $timer) {
                if ($timer['next'] <= $earliestAt) {
                    $earliestId = $id;
                    $earliestAt = $timer['next'];
                }
            }
            if ($earliestId === null) {
                break;
            }
            $this->now = $earliestAt;
            $timer = $this->timers[$earliestId];
            if ($timer['persistent']) {
                $this->timers[$earliestId]['next'] += $timer['interval'];
            } else {
                unset($this->timers[$earliestId]);
            }
            ($timer['tick'])();
        }
        $this->now = $target;
    }
}
