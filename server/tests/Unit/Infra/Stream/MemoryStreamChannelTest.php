<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Infra\Stream\MemoryStreamChannel;

final class MemoryStreamChannelTest extends TestCase
{
    public function testFansOutToAllSubscribersOfChannel(): void
    {
        $channel = new MemoryStreamChannel();
        $received = [];
        $channel->subscribe('note.1', function (StreamEvent $event) use (&$received): void {
            $received[] = 'a:' . $event->data;
        });
        $channel->subscribe('note.1', function (StreamEvent $event) use (&$received): void {
            $received[] = 'b:' . $event->data;
        });
        $channel->subscribe('note.2', function (StreamEvent $event) use (&$received): void {
            $received[] = 'other:' . $event->data;
        });

        $channel->publish('note.1', new StreamEvent('1', null, 'hi'));

        self::assertSame(['a:hi', 'b:hi'], $received);
    }

    public function testUnsubscribeStopsDelivery(): void
    {
        $channel = new MemoryStreamChannel();
        $count = 0;
        $channel->subscribe('note.1', function () use (&$count): void {
            $count++;
        });

        $channel->unsubscribe('note.1');
        $channel->publish('note.1', new StreamEvent(null, null, 'hi'));

        self::assertSame(0, $count);
    }
}
