<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\StreamChannelInterface;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Domain\Stream\StreamReplayStore;

/**
 * 带补发账本的通道装饰器：publish 先落 StreamReplayStore 再扇出，
 * 断线重连方拿 Last-Event-ID 走 store()->after() 补发，
 * 生产方不需要自己同时维护两份存储。
 */
final class ReplayStreamChannel implements StreamChannelInterface
{
    public function __construct(
        private readonly StreamChannelInterface $inner,
        private readonly StreamReplayStore $store,
    ) {
    }

    public function publish(string $channel, StreamEvent $event): void
    {
        $this->store->append($channel, $event);
        $this->inner->publish($channel, $event);
    }

    public function subscribe(string $channel, \Closure $onEvent): void
    {
        $this->inner->subscribe($channel, $onEvent);
    }

    public function unsubscribe(string $channel): void
    {
        $this->inner->unsubscribe($channel);
    }

    public function store(): StreamReplayStore
    {
        return $this->store;
    }
}
