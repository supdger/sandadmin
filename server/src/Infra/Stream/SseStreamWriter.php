<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\BackpressurePolicy;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Domain\Stream\StreamMetrics;
use Sandadmin\Domain\Stream\StreamWriterInterface;
use Workerman\Connection\TcpConnection;

/**
 * workerman 上的 SSE 写出实现：全部走 $connection->send(..., raw) 非阻塞写，
 * 响应头由控制器以 text/event-stream 的 Response 返回（HTTP 协议层对该类型
 * 只发头、保持连接），事件帧在此之后按序写出。
 * 背压挂 onBufferFull/onBufferDrain；onClose 感知客户端断开并链回原回调。
 */
final class SseStreamWriter implements StreamWriterInterface
{
    private bool $closed = false;

    private bool $paused = false;

    /** @var null|\Closure(): void */
    private ?\Closure $gone = null;

    /** @var null|\Closure(TcpConnection): void */
    private ?\Closure $previousClose;

    public function __construct(
        private readonly TcpConnection $connection,
        private readonly BackpressurePolicy $policy = BackpressurePolicy::Pause,
        private readonly ?StreamMetrics $metrics = null,
    ) {
        $close = $connection->onClose;
        $this->previousClose = $close instanceof \Closure ? $close : null;
        $connection->onClose = function (TcpConnection $conn): void {
            $this->clientGone();
            $previous = $this->previousClose;
            if ($previous !== null) {
                $previous($conn);
            }
        };
        $connection->onBufferFull = function (): void {
            $this->paused = true;
            $this->metrics?->bufferFull();
        };
        $connection->onBufferDrain = function (): void {
            $this->paused = false;
        };
    }

    public function send(StreamEvent $event): bool
    {
        if ($this->closed) {
            return false;
        }
        if ($this->paused && $this->policy === BackpressurePolicy::Drop) {
            $this->metrics?->eventDropped('backpressure');

            return false;
        }
        $sent = $this->connection->send(SseFraming::frame($event), true);
        if ($sent === false) {
            $this->clientGone();

            return false;
        }

        return true;
    }

    public function heartbeat(): bool
    {
        if ($this->closed) {
            return false;
        }
        $sent = $this->connection->send(SseFraming::heartbeat(), true);
        if ($sent === false) {
            $this->clientGone();

            return false;
        }

        return true;
    }

    public function fail(string $code, string $message): void
    {
        if ($this->closed) {
            return;
        }
        $this->connection->close(SseFraming::error($code, $message), true);
        $this->closed = true;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->connection->close(null, true);
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        if ($this->closed) {
            return true;
        }

        return $this->connection->getStatus() !== TcpConnection::STATUS_ESTABLISHED;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function whenClientGone(\Closure $gone): void
    {
        $this->gone = $gone;
    }

    private function clientGone(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $gone = $this->gone;
        if ($gone !== null) {
            $gone();
        }
    }
}
