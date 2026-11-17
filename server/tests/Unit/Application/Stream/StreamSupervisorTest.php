<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Stream;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Stream\StreamSupervisor;
use Sandadmin\Domain\Stream\StreamCloseReason;
use Sandadmin\Domain\Stream\StreamLimitException;
use Sandadmin\Infra\Stream\MemoryStreamMetrics;
use Sandadmin\Infra\Stream\MemoryStreamWriter;
use Sandadmin\Infra\Stream\StreamProfile;
use Sandadmin\Tests\Support\ManualTicker;
use Sandadmin\Tests\Support\MapPermissionProbe;

final class StreamSupervisorTest extends TestCase
{
    public function testRejectsOverPerProcessLimit(): void
    {
        $metrics = new MemoryStreamMetrics();
        $supervisor = new StreamSupervisor(
            StreamProfile::fromArray('web', ['stream_limit' => 2]),
            new ManualTicker(),
            $metrics,
        );
        $probe = new MapPermissionProbe(['u1' => 1]);

        $supervisor->open('u1', 1, new MemoryStreamWriter(), $probe)->start();
        $supervisor->open('u1', 1, new MemoryStreamWriter(), $probe)->start();

        self::assertSame(2, $supervisor->activeCount());
        self::assertSame(2, $metrics->counts()['opened']);

        try {
            $supervisor->open('u1', 1, new MemoryStreamWriter(), $probe);
            self::fail('第三路应被限流拒绝');
        } catch (StreamLimitException $exception) {
            self::assertSame('STREAM_LIMIT_REACHED', $exception->errorCode());
            self::assertSame(429, $exception->httpStatus());
        }
    }

    public function testRevokeSubjectDisconnectsOnlyThatSubjectsStreams(): void
    {
        $supervisor = new StreamSupervisor(
            StreamProfile::fromArray('web', []),
            new ManualTicker(),
            new MemoryStreamMetrics(),
        );
        $probe = new MapPermissionProbe(['u1' => 1, 'u2' => 1]);
        $writer1 = new MemoryStreamWriter();
        $writer2 = new MemoryStreamWriter();
        $writer3 = new MemoryStreamWriter();
        $supervisor->open('u1', 1, $writer1, $probe)->start();
        $supervisor->open('u1', 1, $writer2, $probe)->start();
        $supervisor->open('u2', 1, $writer3, $probe)->start();

        self::assertSame(2, $supervisor->revokeSubject('u1'));

        self::assertTrue($writer1->isClosed());
        self::assertTrue($writer2->isClosed());
        self::assertFalse($writer3->isClosed());
        self::assertSame(1, $supervisor->activeCount());
        self::assertStringContainsString('STREAM_AUTHZ_REVOKED', $writer1->output());
    }

    public function testCloseReasonsFlowIntoMetrics(): void
    {
        $metrics = new MemoryStreamMetrics();
        $supervisor = new StreamSupervisor(
            StreamProfile::fromArray('web', []),
            new ManualTicker(),
            $metrics,
        );
        $probe = new MapPermissionProbe(['u1' => 1]);
        $session = $supervisor->open('u1', 1, new MemoryStreamWriter(), $probe);
        $session->start();

        $session->close(StreamCloseReason::Completed);

        self::assertSame(0, $supervisor->activeCount());
        self::assertSame(1, $metrics->counts()['closed']['completed'] ?? 0);
        self::assertSame(0, $metrics->activeCount());
    }

    public function testClientGoneReasonRecorded(): void
    {
        $metrics = new MemoryStreamMetrics();
        $supervisor = new StreamSupervisor(
            StreamProfile::fromArray('web', []),
            new ManualTicker(),
            $metrics,
        );
        $probe = new MapPermissionProbe(['u1' => 1]);
        $writer = new MemoryStreamWriter();
        $supervisor->open('u1', 1, $writer, $probe)->start();

        $writer->simulateClientGone();

        self::assertSame(0, $supervisor->activeCount());
        self::assertSame(1, $metrics->counts()['closed']['client_gone'] ?? 0);
    }
}
