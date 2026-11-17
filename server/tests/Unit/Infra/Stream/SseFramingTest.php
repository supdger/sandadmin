<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Infra\Stream\SseFraming;

final class SseFramingTest extends TestCase
{
    public function testFramesIdNameAndData(): void
    {
        $frame = SseFraming::frame(new StreamEvent('42', 'note', 'hello'));

        self::assertSame("id: 42\nevent: note\ndata: hello\n\n", $frame);
    }

    public function testSplitsMultilineData(): void
    {
        $frame = SseFraming::frame(new StreamEvent(null, null, "a\nb\r\nc"));

        self::assertSame("data: a\ndata: b\ndata: c\n\n", $frame);
    }

    public function testStripsNewlinesFromIdAndName(): void
    {
        $frame = SseFraming::frame(new StreamEvent("1\n2", "ev\nent", 'x'));

        self::assertSame("id: 12\nevent: event\ndata: x\n\n", $frame);
    }

    public function testHeartbeatIsCommentLine(): void
    {
        self::assertSame(": heartbeat\n\n", SseFraming::heartbeat());
    }

    public function testErrorFrameCarriesCodeAndMessage(): void
    {
        $frame = SseFraming::error('STREAM_IDLE_TIMEOUT', '空闲超时');

        self::assertStringContainsString('event: error', $frame);
        $line = '';
        foreach (explode("\n", $frame) as $row) {
            if (str_starts_with($row, 'data: ')) {
                $line = substr($row, 6);
            }
        }
        $data = json_decode($line, true);
        self::assertIsArray($data);
        self::assertSame('STREAM_IDLE_TIMEOUT', $data['code']);
        self::assertSame('空闲超时', $data['message']);
    }
}
