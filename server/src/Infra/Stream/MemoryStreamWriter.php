<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\BackpressurePolicy;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Domain\Stream\StreamMetrics;
use Sandadmin\Domain\Stream\StreamWriterInterface;

/**
 * 缓冲写出的 StreamWriterInterface 实现：帧串累积在内存里，
 * 由 output() 一次取回——即「非流式路径」。单测与 MCP 无状态
 * 响应这类先收齐再整体返回的场景用它。
 */
final class MemoryStreamWriter implements StreamWriterInterface
{
    private string $output = '';

    private bool $closed = false;

    private bool $paused = false;

    /** @var null|\Closure(): void */
    private ?\Closure $gone = null;

    public function __construct(
        private readonly BackpressurePolicy $policy = BackpressurePolicy::Pause,
        private readonly ?StreamMetrics $metrics = null,
    ) {
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
        $this->output .= SseFraming::frame($event);

        return true;
    }

    public function heartbeat(): bool
    {
        if ($this->closed) {
            return false;
        }
        $this->output .= SseFraming::heartbeat();

        return true;
    }

    public function fail(string $code, string $message): void
    {
        if ($this->closed) {
            return;
        }
        $this->output .= SseFraming::error($code, $message);
        $this->closed = true;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function whenClientGone(\Closure $gone): void
    {
        $this->gone = $gone;
    }

    /** 累积的全部帧串（非流式路径一次取回作响应体）。 */
    public function output(): string
    {
        return $this->output;
    }

    /** 测试桩：模拟发送缓冲触顶（Drop 策略下 send 开始丢弃）。 */
    public function pause(): void
    {
        $this->paused = true;
    }

    /** 测试桩：模拟缓冲排空，暂停窗口结束。 */
    public function drain(): void
    {
        $this->paused = false;
    }

    /** 测试桩：模拟客户端断开，触发 whenClientGone 回调一次。 */
    public function simulateClientGone(): void
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
