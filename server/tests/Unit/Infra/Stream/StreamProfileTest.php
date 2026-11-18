<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Stream\BackpressurePolicy;
use Sandadmin\Infra\Stream\StreamProfile;

final class StreamProfileTest extends TestCase
{
    public function testDefaultsFromEmptyConfig(): void
    {
        $profile = StreamProfile::fromArray('web', []);

        self::assertSame('web', $profile->role());
        self::assertSame(80, $profile->streamLimit());
        self::assertSame(30, $profile->idleSeconds());
        self::assertSame(300, $profile->streamSeconds());
        self::assertSame(15, $profile->heartbeatSeconds());
        self::assertSame(30, $profile->probeSeconds());
        self::assertSame(BackpressurePolicy::Pause, $profile->backpressure());
    }

    public function testParsesConfiguredValues(): void
    {
        $profile = StreamProfile::fromArray('ai_stream', [
            'stream_limit' => 100,
            'idle_seconds' => 45,
            'stream_seconds' => 240,
            'backpressure' => 'drop',
            'max_requests' => 5000,
        ]);

        self::assertSame(100, $profile->streamLimit());
        self::assertSame(45, $profile->idleSeconds());
        self::assertSame(240, $profile->streamSeconds());
        self::assertSame(BackpressurePolicy::Drop, $profile->backpressure());
        self::assertSame(5000, $profile->maxRequests());
    }

    public function testRejectsStreamSecondsOverHardCap(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StreamProfile::fromArray('web', ['stream_seconds' => 301]);
    }

    public function testRejectsZeroStreamLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StreamProfile::fromArray('web', ['stream_limit' => 0]);
    }

    public function testRejectsNonPositiveTimeouts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StreamProfile::fromArray('web', ['probe_seconds' => 0]);
    }

    public function testUnknownBackpressureFallsBackToPause(): void
    {
        $profile = StreamProfile::fromArray('web', ['backpressure' => 'bogus']);

        self::assertSame(BackpressurePolicy::Pause, $profile->backpressure());
    }
}
