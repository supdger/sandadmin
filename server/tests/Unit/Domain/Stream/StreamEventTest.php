<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\StreamEvent;

final class StreamEventTest extends TestCase
{
    public function testPayloadRoundTrips(): void
    {
        $event = new StreamEvent('17', 'note', '含中文/斜杠');

        $restored = StreamEvent::fromPayload($event->toPayload());

        self::assertNotNull($restored);
        self::assertSame('17', $restored->id);
        self::assertSame('note', $restored->name);
        self::assertSame('含中文/斜杠', $restored->data);
    }

    public function testFromPayloadReturnsNullOnGarbage(): void
    {
        self::assertNull(StreamEvent::fromPayload('{broken'));
        self::assertNull(StreamEvent::fromPayload('42'));
    }

    public function testFromPayloadToleratesMissingFields(): void
    {
        $restored = StreamEvent::fromPayload('{"data":"x"}');

        self::assertNotNull($restored);
        self::assertNull($restored->id);
        self::assertNull($restored->name);
        self::assertSame('x', $restored->data);
    }
}
