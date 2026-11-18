<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Stream\StreamSession;
use Sandadmin\Domain\Stream\StreamCloseReason;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Infra\Stream\MemoryStreamWriter;
use Sandadmin\Infra\Stream\StreamProfile;
use Sandadmin\Tests\Support\ManualTicker;
use Sandadmin\Tests\Support\MapPermissionProbe;

final class StreamSessionTest extends TestCase
{
    /** @return array{0: StreamSession, 1: MemoryStreamWriter, 2: ManualTicker, 3: MapPermissionProbe} */
    private function makeSession(int $probeSeconds = 30, int $streamSeconds = 300): array
    {
        $ticker = new ManualTicker();
        $writer = new MemoryStreamWriter();
        $probe = new MapPermissionProbe(['u1' => 7]);
        $profile = StreamProfile::fromArray('web', [
            'idle_seconds' => 30,
            'heartbeat_seconds' => 15,
            'probe_seconds' => $probeSeconds,
            'stream_seconds' => $streamSeconds,
        ]);
        $session = new StreamSession(1, 'u1', $writer, $profile, $ticker, $probe, 7);
        $session->start();

        return [$session, $writer, $ticker, $probe];
    }

    public function testHeartbeatFiresOnInterval(): void
    {
        [$session, $writer, $ticker] = $this->makeSession();

        $ticker->advance(15.0);

        self::assertStringContainsString(': heartbeat', $writer->output());
        self::assertFalse($session->isClosed());
    }

    public function testIdleTimeoutClosesWithRegisteredCode(): void
    {
        [$session, $writer, $ticker] = $this->makeSession();
        $closed = [];
        $session->addCloseListener(function (StreamCloseReason $reason) use (&$closed): void {
            $closed[] = $reason;
        });

        // 心跳 15s 先走（未空闲），30s 时距上次内容 ≥30 判空闲
        $ticker->advance(31.0);

        self::assertTrue($session->isClosed());
        self::assertSame([StreamCloseReason::Idle], $closed);
        self::assertStringContainsString('STREAM_IDLE_TIMEOUT', $writer->output());
    }

    public function testContentSendResetsIdleClock(): void
    {
        [$session, , $ticker] = $this->makeSession();

        $ticker->advance(20.0);
        $session->send(new StreamEvent('1', null, 'a'));
        $ticker->advance(20.0);

        self::assertFalse($session->isClosed());

        // t0+60 心跳刻度时距上次内容 40 秒 > 空闲 30 秒
        $ticker->advance(21.0);
        self::assertTrue($session->isClosed());
    }

    public function testProbeDriftRevokes(): void
    {
        [$session, $writer, $ticker, $probe] = $this->makeSession();

        $probe->set('u1', 8);
        $ticker->advance(30.0);

        self::assertTrue($session->isClosed());
        self::assertStringContainsString('STREAM_AUTHZ_REVOKED', $writer->output());
    }

    public function testProbeFailureFailsClosed(): void
    {
        [$session, $writer, $ticker, $probe] = $this->makeSession();

        $probe->set('u1', null);
        $ticker->advance(30.0);

        self::assertTrue($session->isClosed());
        self::assertStringContainsString('STREAM_AUTHZ_REVOKED', $writer->output());
    }

    public function testDurationLimitClosesOnce(): void
    {
        [$session, $writer, $ticker] = $this->makeSession(probeSeconds: 1000, streamSeconds: 300);

        // 周期发内容防空闲断开，拨到 300 秒硬顶
        for ($i = 0; $i < 10; $i++) {
            $session->send(new StreamEvent((string) $i, null, 'x'));
            $ticker->advance(29.0);
        }
        $ticker->advance(10.0);

        self::assertTrue($session->isClosed());
        self::assertStringContainsString('STREAM_DURATION_LIMIT', $writer->output());
    }

    public function testClientGoneClosesSessionWithoutErrorFrame(): void
    {
        [$session, $writer] = $this->makeSession();
        $closed = [];
        $session->addCloseListener(function (StreamCloseReason $reason) use (&$closed): void {
            $closed[] = $reason;
        });

        $writer->simulateClientGone();

        self::assertTrue($session->isClosed());
        self::assertSame([StreamCloseReason::ClientGone], $closed);
        self::assertStringNotContainsString('event: error', $writer->output());
    }

    public function testCloseIsIdempotentAndCancelsTimers(): void
    {
        [$session, $writer, $ticker] = $this->makeSession();
        $closed = 0;
        $session->addCloseListener(function () use (&$closed): void {
            $closed++;
        });

        $session->close(StreamCloseReason::Completed);
        $session->close(StreamCloseReason::Failed);
        $ticker->advance(1000.0);

        self::assertSame(1, $closed);
        self::assertFalse($session->send(new StreamEvent(null, null, 'x')));
        // Completed 不发错误帧；定时器已撤，拨 1000 秒也不再有心跳落进缓冲
        self::assertSame('', $writer->output());
    }
}
