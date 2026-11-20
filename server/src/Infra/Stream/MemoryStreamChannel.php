<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\StreamChannelInterface;
use Sandadmin\Domain\Stream\StreamEvent;

/**
 * 进程内发布/订阅：同 worker 内同步扇出。单测一律用它；
 * 单机单进程部署也可直接用（跨进程要换 RedisStreamChannel）。
 */
final class MemoryStreamChannel implements StreamChannelInterface
{
    /** @var array<string, list<\Closure(StreamEvent): void>> */
    private array $subscribers = [];

    public function publish(string $channel, StreamEvent $event): void
    {
        foreach ($this->subscribers[$channel] ?? [] as $onEvent) {
            ($onEvent)($event);
        }
    }

    public function subscribe(string $channel, \Closure $onEvent): void
    {
        $this->subscribers[$channel][] = $onEvent;
    }

    public function unsubscribe(string $channel): void
    {
        unset($this->subscribers[$channel]);
    }
}
