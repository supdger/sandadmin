<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Infra\Stream\MemoryStreamChannel;
use Sandadmin\Infra\Stream\MemoryReplayStore;
use Sandadmin\Infra\Stream\ReplayStreamChannel;

final class ReplayStreamChannelTest extends TestCase
{
    public function testPublishFansOutAndLandsInStore(): void
    {
        $store = new MemoryReplayStore();
        $channel = new ReplayStreamChannel(new MemoryStreamChannel(), $store);
        $received = [];
        $channel->subscribe('note.1', function (StreamEvent $event) use (&$received): void {
            $received[] = $event->data;
        });

        $channel->publish('note.1', new StreamEvent('1', null, 'a'));

        self::assertSame(['a'], $received);
        self::assertCount(1, $store->after('note.1', null));
    }

    public function testReplayAfterLastEventId(): void
    {
        $channel = new ReplayStreamChannel(new MemoryStreamChannel(), new MemoryReplayStore());
        $channel->publish('note.1', new StreamEvent('1', null, 'a'));
        $channel->publish('note.1', new StreamEvent('2', null, 'b'));

        // 模拟断线重连：客户端拿 Last-Event-ID=1 补发
        self::assertSame(['b'], array_map(
            static fn (StreamEvent $event): string => $event->data,
            $channel->store()->after('note.1', '1'),
        ));
    }

    public function testUnsubscribeProxiesToInner(): void
    {
        $channel = new ReplayStreamChannel(new MemoryStreamChannel(), new MemoryReplayStore());
        $count = 0;
        $channel->subscribe('c', function () use (&$count): void {
            $count++;
        });

        $channel->unsubscribe('c');
        $channel->publish('c', new StreamEvent(null, null, 'x'));

        self::assertSame(0, $count);
    }
}
