<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\BackpressurePolicy;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Infra\Stream\MemoryStreamMetrics;
use Sandadmin\Infra\Stream\MemoryStreamWriter;

final class MemoryStreamWriterTest extends TestCase
{
    public function testBuffersFramesForNonStreamingOutput(): void
    {
        $writer = new MemoryStreamWriter();
        $writer->send(new StreamEvent('1', 'note', 'a'));
        $writer->heartbeat();
        $writer->send(new StreamEvent('2', null, 'b'));

        self::assertSame(
            "id: 1\nevent: note\ndata: a\n\n: heartbeat\n\nid: 2\ndata: b\n\n",
            $writer->output(),
        );
    }

    public function testDropPolicyDiscardsWhilePaused(): void
    {
        $metrics = new MemoryStreamMetrics();
        $writer = new MemoryStreamWriter(BackpressurePolicy::Drop, $metrics);
        $writer->send(new StreamEvent(null, null, 'a'));
        $writer->pause();

        self::assertTrue($writer->isPaused());
        self::assertFalse($writer->send(new StreamEvent(null, null, 'b')));

        $writer->drain();
        self::assertFalse($writer->isPaused());
        self::assertTrue($writer->send(new StreamEvent(null, null, 'c')));
        self::assertSame(1, $metrics->counts()['dropped']['backpressure'] ?? 0);
        self::assertStringNotContainsString('data: b', $writer->output());
        self::assertStringContainsString('data: c', $writer->output());
    }

    public function testPausePolicyKeepsBufferingWhilePaused(): void
    {
        $writer = new MemoryStreamWriter(BackpressurePolicy::Pause);
        $writer->pause();

        self::assertTrue($writer->send(new StreamEvent(null, null, 'a')));
        self::assertStringContainsString('data: a', $writer->output());
    }

    public function testFailWritesErrorEventAndCloses(): void
    {
        $writer = new MemoryStreamWriter();
        $writer->fail('STREAM_DURATION_LIMIT', '已达流式时长上限');

        self::assertTrue($writer->isClosed());
        self::assertFalse($writer->send(new StreamEvent(null, null, 'x')));
        self::assertStringContainsString('event: error', $writer->output());
        self::assertStringContainsString('STREAM_DURATION_LIMIT', $writer->output());
    }

    public function testClientGoneFiresCallbackOnceAndCloses(): void
    {
        $writer = new MemoryStreamWriter();
        $gone = 0;
        $writer->whenClientGone(function () use (&$gone): void {
            $gone++;
        });

        $writer->simulateClientGone();
        $writer->simulateClientGone();

        self::assertSame(1, $gone);
        self::assertTrue($writer->isClosed());
    }
}
