<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\DemoNote;

use Sandadmin\Application\Stream\StreamRuntime;
use Sandadmin\Application\Stream\StreamSupervisor;
use Sandadmin\Domain\Stream\StreamCloseReason;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Domain\Stream\StreamLimitException;
use Sandadmin\Domain\Stream\StreamTicker;
use Sandadmin\Infra\DevBuild;
use Sandadmin\Infra\Stream\MemoryStreamChannel;
use Sandadmin\Infra\Stream\MemoryStreamMetrics;
use Sandadmin\Infra\Stream\MemoryStreamReplayStore;
use Sandadmin\Infra\Stream\ReplayStreamChannel;
use Sandadmin\Infra\Stream\SseStreamWriter;
use Sandadmin\Infra\Stream\WorkermanTicker;
use support\Request;
use support\Response;
use Workerman\Connection\TcpConnection;

/**
 * 开发期 SSE 演示/压测端点（整目录随生产包剔除，见 tools/dev-only-paths.txt）。
 * - GET /api/dev/stream/sse：按 interval_ms 每拍推一条 demo 事件，slow_ms
 *   模拟慢上游首事件延迟，count>0 到量正常收流；Last-Event-ID 触发补发。
 * - GET /api/dev/stream/ping：快请求基准，供压测对照「慢上游不阻塞普通请求」。
 * supervisor/定时器可注入，测试不依赖 Workerman 运行环境。
 * 通道与补发账本是进程级单例：断线重连要在本进程内补发，口径同 J-5。
 */
final class SseDemoController
{
    private const CHANNEL_PREFIX = 'demo.';

    private static ?ReplayStreamChannel $sharedChannel = null;

    public function __construct(
        private readonly ?StreamSupervisor $supervisor = null,
        private readonly ?StreamTicker $ticker = null,
        private readonly ?ReplayStreamChannel $channel = null,
    ) {
    }

    public function events(Request $request): Response
    {
        if (!DevBuild::enabled()) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Not Found');
        }
        $connection = $request->connection;
        if (!$connection instanceof TcpConnection) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Not Found');
        }
        $intervalMs = self::intParam($request, 'interval_ms', 1000, 1, 300_000);
        $slowMs = self::intParam($request, 'slow_ms', 0, 0, 300_000);
        $count = self::intParam($request, 'count', 0, 0, 100_000);

        $supervisor = $this->supervisor ?? StreamRuntime::supervisor('web');
        $ticker = $this->ticker ?? new WorkermanTicker();
        $writer = new SseStreamWriter($connection, $supervisor->profile()->backpressure());
        try {
            $session = $supervisor->open('dev-sse', 0, $writer, new DemoStreamProbe());
        } catch (StreamLimitException $exception) {
            return new Response(
                $exception->httpStatus(),
                ['Content-Type' => 'application/json; charset=utf-8'],
                json_encode(
                    ['code' => $exception->errorCode(), 'message' => $exception->getMessage()],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ),
            );
        }
        $session->start();
        $session->send(new StreamEvent((string) $session->id(), 'open', '{"subject":"dev-sse"}'));

        $channel = $this->channel ?? self::channel();

        // Last-Event-ID 补发：游标之后按序重放，再进实时流
        $lastEventId = $request->header('last-event-id');
        if (is_string($lastEventId) && $lastEventId !== '') {
            foreach ($channel->store()->after(self::CHANNEL_PREFIX . 'all', $lastEventId) as $event) {
                if (!$session->send($event)) {
                    break;
                }
            }
        }

        $channelName = self::CHANNEL_PREFIX . $session->id();
        $channel->subscribe($channelName, function (StreamEvent $event) use ($session): void {
            $session->send($event);
        });

        // 生产方定时器：slow_ms 窗口内空转模拟慢上游（不阻塞进程），
        // count 到量按 Completed 正常收流；关流即撤订阅、撤定时器、写总账。
        $emitAfter = $ticker->now() + $slowMs / 1000.0;
        $emitted = 0;
        $producerId = $ticker->every(
            $intervalMs / 1000.0,
            function () use (&$emitted, $count, $emitAfter, $channelName, $channel, $session, $ticker): void {
                if ($session->isClosed() || $ticker->now() < $emitAfter) {
                    return;
                }
                $emitted++;
                $event = new StreamEvent((string) $emitted, 'demo', json_encode(
                    ['seq' => $emitted, 'at' => round($ticker->now(), 3)],
                    JSON_THROW_ON_ERROR,
                ));
                $channel->publish($channelName, $event);
                // 总账 channel：游标与单路事件同序，Last-Event-ID 断线重连按它补发
                $channel->publish(self::CHANNEL_PREFIX . 'all', $event);
                if ($count > 0 && $emitted >= $count) {
                    $session->close(StreamCloseReason::Completed);
                }
            },
        );
        $session->addCloseListener(function () use ($channel, $channelName, $producerId, $ticker): void {
            $ticker->cancel($producerId);
            $channel->unsubscribe($channelName);
        });

        return new Response(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function ping(Request $request): Response
    {
        $metrics = StreamRuntime::metrics();
        $active = $metrics instanceof MemoryStreamMetrics ? $metrics->activeCount() : 0;

        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(
            ['code' => 200, 'message' => 'ok', 'data' => ['streams' => $active]],
            JSON_THROW_ON_ERROR,
        ));
    }

    private static function channel(): ReplayStreamChannel
    {
        if (self::$sharedChannel === null) {
            self::$sharedChannel = new ReplayStreamChannel(new MemoryStreamChannel(), new MemoryStreamReplayStore(1000));
        }

        return self::$sharedChannel;
    }

    private static function intParam(Request $request, string $key, int $default, int $min, int $max): int
    {
        $value = $request->get($key);
        if (!is_string($value) && !is_int($value)) {
            return $default;
        }
        $number = (int) $value;

        return max($min, min($max, $number));
    }
}
