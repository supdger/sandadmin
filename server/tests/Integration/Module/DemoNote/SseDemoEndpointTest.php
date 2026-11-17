<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\DemoNote;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Stream\StreamSupervisor;
use Sandadmin\Infra\Stream\MemoryStreamChannel;
use Sandadmin\Infra\Stream\MemoryStreamMetrics;
use Sandadmin\Infra\Stream\MemoryStreamReplayStore;
use Sandadmin\Infra\Stream\ReplayStreamChannel;
use Sandadmin\Infra\Stream\StreamProfile;
use Sandadmin\Module\DemoNote\SseDemoController;
use Sandadmin\Tests\Support\ManualTicker;
use support\Request;
use Workerman\Connection\TcpConnection;
use Workerman\Events\Select;

/**
 * 开发期 SSE 演示端点的端到端：socketpair 上挂真实 TcpConnection，
 * ManualTicker 替代 Workerman 定时器拨出事件、心跳、收流与补发帧。
 */
final class SseDemoEndpointTest extends TestCase
{
    /** @return array{0: SseDemoController, 1: ManualTicker, 2: ReplayStreamChannel, 3: StreamSupervisor} */
    private static function rig(int $streamLimit = 80): array
    {
        $ticker = new ManualTicker();
        $metrics = new MemoryStreamMetrics();
        $supervisor = new StreamSupervisor(
            StreamProfile::fromArray('web', [
                'stream_limit' => $streamLimit,
                'heartbeat_seconds' => 15,
                'idle_seconds' => 30,
                'probe_seconds' => 30,
                'stream_seconds' => 300,
            ]),
            $ticker,
            $metrics,
        );
        $channel = new ReplayStreamChannel(new MemoryStreamChannel(), new MemoryStreamReplayStore());
        $controller = new SseDemoController($supervisor, $ticker, $channel);

        return [$controller, $ticker, $channel, $supervisor];
    }

    /**
     * @return array{0: Request, 1: resource}
     */
    private static function requestOn(string $query, string $headers = ''): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        $connection = new TcpConnection(new Select(), $pair[0]);
        stream_set_blocking($pair[1], false);
        $request = new Request("GET /api/dev/stream/sse{$query} HTTP/1.1\r\nHost: example.test\r\n{$headers}\r\n");
        $request->connection = $connection;

        return [$request, $pair[1]];
    }

    /** @param resource $peer */
    private static function readPeer($peer): string
    {
        $buffer = '';
        while (true) {
            $chunk = fread($peer, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    public function testEventsEmitsFramesAndClosesOnCount(): void
    {
        [$controller, $ticker, , $supervisor] = self::rig();
        [$request, $peer] = self::requestOn('?interval_ms=50&count=3');

        $response = $controller->events($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/event-stream', $response->getHeader('Content-Type'));
        self::assertStringContainsString('event: open', self::readPeer($peer));

        $ticker->advance(0.2);
        $body = self::readPeer($peer);

        self::assertStringContainsString("id: 1\nevent: demo", $body);
        self::assertStringContainsString('id: 3', $body);
        self::assertSame(0, $supervisor->activeCount(), 'count 到量应按 Completed 收流出账');
    }

    public function testHeartbeatKeepsAliveDuringIdleWindow(): void
    {
        [$controller, $ticker] = self::rig();
        [$request, $peer] = self::requestOn('?interval_ms=20000');

        $controller->events($request);
        self::readPeer($peer);

        $ticker->advance(15.0);
        self::assertStringContainsString(': heartbeat', self::readPeer($peer));
    }

    public function testLastEventIdReplaysBeforeLiveEvents(): void
    {
        [$controller, $ticker, $channel] = self::rig();
        [$first, $firstPeer] = self::requestOn('?interval_ms=50&count=3');
        $controller->events($first);
        $ticker->advance(0.2);
        self::readPeer($firstPeer);

        // 断线重连：Last-Event-ID=1 → 补发游标 1 之后的 2、3
        [$second, $secondPeer] = self::requestOn('?count=0', "Last-Event-ID: 1\r\n");
        $controller->events($second);
        $replayed = self::readPeer($secondPeer);

        self::assertStringContainsString('id: 2', $replayed);
        self::assertStringContainsString('id: 3', $replayed);
        self::assertStringNotContainsString('id: 1\n', $replayed);
    }

    public function testOverLimitReturns429WithRegisteredCode(): void
    {
        [$controller, , , $supervisor] = self::rig(streamLimit: 1);
        // 对端句柄要留着：peer 释放即断开，session 会按 ClientGone 立刻收
        [$first, $firstPeer] = self::requestOn('?interval_ms=1000');
        $controller->events($first);
        self::assertSame(1, $supervisor->activeCount());

        [$second] = self::requestOn('?interval_ms=1000');
        $response = $controller->events($second);

        self::assertSame(429, $response->getStatusCode());
        self::assertStringContainsString('STREAM_LIMIT_REACHED', (string) $response->rawBody());
    }

    public function testPingReportsActiveStreamCount(): void
    {
        [$controller, , , $supervisor] = self::rig();
        $request = new Request("GET /api/dev/stream/ping HTTP/1.1\r\nHost: example.test\r\n\r\n");

        $response = $controller->ping($request);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->rawBody(), true);
        self::assertIsArray($body);
        self::assertSame(200, $body['code']);
        $data = $body['data'] ?? null;
        self::assertIsArray($data);
        self::assertIsInt($data['streams'] ?? null);
    }

    public function testMissingConnectionReturns404(): void
    {
        [$controller] = self::rig();
        $request = new Request("GET /api/dev/stream/sse HTTP/1.1\r\nHost: example.test\r\n\r\n");

        self::assertSame(404, $controller->events($request)->getStatusCode());
    }
}
