<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Infra\Stream\MemoryStreamReplayStore;

final class MemoryStreamReplayStoreTest extends TestCase
{
    public function testAfterCursorReturnsSubsequentEvents(): void
    {
        $store = new MemoryStreamReplayStore();
        $store->append('c', new StreamEvent('1', null, 'a'));
        $store->append('c', new StreamEvent('2', null, 'b'));
        $store->append('c', new StreamEvent('3', null, 'c'));

        self::assertSame(['b', 'c'], array_map(
            static fn (StreamEvent $event): string => $event->data,
            $store->after('c', '1'),
        ));
    }

    public function testNullCursorReturnsEverything(): void
    {
        $store = new MemoryStreamReplayStore();
        $store->append('c', new StreamEvent('1', null, 'a'));
        $store->append('c', new StreamEvent('2', null, 'b'));

        self::assertCount(2, $store->after('c', null));
    }

    public function testUnknownCursorFallsBackToAll(): void
    {
        $store = new MemoryStreamReplayStore();
        $store->append('c', new StreamEvent('1', null, 'a'));
        $store->append('c', new StreamEvent('2', null, 'b'));

        self::assertCount(2, $store->after('c', 'missing'));
    }

    public function testAutoAssignsMonotonicCursorWhenIdMissing(): void
    {
        $store = new MemoryStreamReplayStore();
        $store->append('c', new StreamEvent(null, null, 'a'));
        $store->append('c', new StreamEvent(null, null, 'b'));

        $events = $store->after('c', null);
        self::assertSame('c:1', $events[0]->id);
        self::assertSame('c:2', $events[1]->id);
    }

    public function testDropsOldestWhenOverLimit(): void
    {
        $store = new MemoryStreamReplayStore(2);
        $store->append('c', new StreamEvent('1', null, 'a'));
        $store->append('c', new StreamEvent('2', null, 'b'));
        $store->append('c', new StreamEvent('3', null, 'c'));

        self::assertSame(['2', '3'], array_map(
            static fn (StreamEvent $event): ?string => $event->id,
            $store->after('c', null),
        ));
    }
}
