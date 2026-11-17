<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Stream;

use Sandadmin\Domain\Stream\ProcessProfileInterface;
use Sandadmin\Domain\Stream\StreamCloseReason;
use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Domain\Stream\StreamPermissionProbe;
use Sandadmin\Domain\Stream\StreamTicker;
use Sandadmin\Domain\Stream\StreamWriterInterface;

/**
 * 一路流式连接的生命周期：SSE 心跳（每 heartbeatSeconds 一行注释）、
 * 无内容空闲超时、总时长上限（≤300 秒）、每 probeSeconds 复查一次
 * 权限版本号（J-5 定 30 秒）——版本漂移或探不到即按撤权断开。
 * 定时全走注入的 StreamTicker，本类不感知 Workerman\Timer。
 */
final class StreamSession
{
    private const CLOSE_MESSAGES = [
        'idle' => '空闲超时，连接已断开',
        'duration_limit' => '已达流式时长上限',
        'revoked' => '权限已变更，连接已断开',
        'failed' => '流式输出失败',
    ];

    /** @var list<int> */
    private array $timerIds = [];

    /** @var list<\Closure(StreamCloseReason): void> */
    private array $closeListeners = [];

    private bool $closed = false;

    private float $lastContentAt;

    private readonly float $openedAt;

    public function __construct(
        private readonly int $id,
        private readonly string $subject,
        private readonly StreamWriterInterface $writer,
        private readonly ProcessProfileInterface $profile,
        private readonly StreamTicker $ticker,
        private readonly StreamPermissionProbe $probe,
        private readonly int $authzVersion,
    ) {
        $this->openedAt = $ticker->now();
        $this->lastContentAt = $this->openedAt;
    }

    /** 挂载定时器与断开感知；开流后必须调一次。 */
    public function start(): void
    {
        $writer = $this->writer;
        $writer->whenClientGone(function (): void {
            $this->close(StreamCloseReason::ClientGone);
        });
        $this->timerIds[] = $this->ticker->every($this->profile->heartbeatSeconds(), function (): void {
            $this->tickHeartbeat();
        });
        $this->timerIds[] = $this->ticker->every($this->profile->probeSeconds(), function (): void {
            $this->tickProbe();
        });
        $this->timerIds[] = $this->ticker->after($this->profile->streamSeconds(), function (): void {
            $this->close(StreamCloseReason::DurationLimit);
        });
    }

    /** 写一条事件；被背压/已关闭时返回 false。成功写出才算内容进度。 */
    public function send(StreamEvent $event): bool
    {
        if ($this->closed) {
            return false;
        }
        $sent = $this->writer->send($event);
        if ($sent) {
            $this->lastContentAt = $this->ticker->now();
        }

        return $sent;
    }

    /** 幂等关流：撤定时器 → 按原因发错误帧或直接结束 → 通知监听方。 */
    public function close(StreamCloseReason $reason): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        foreach ($this->timerIds as $timerId) {
            $this->ticker->cancel($timerId);
        }
        $this->timerIds = [];
        $code = $reason->errorCode();
        if ($code !== null) {
            $this->writer->fail($code, self::CLOSE_MESSAGES[$reason->value] ?? '流式连接已结束');
        } else {
            $this->writer->close();
        }
        foreach ($this->closeListeners as $listener) {
            $listener($reason);
        }
    }

    /** @param \Closure(StreamCloseReason): void $listener 关闭时按登记顺序回调一次 */
    public function addCloseListener(\Closure $listener): void
    {
        $this->closeListeners[] = $listener;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function isPaused(): bool
    {
        return $this->writer->isPaused();
    }

    public function openedAt(): float
    {
        return $this->openedAt;
    }

    public function lastContentAt(): float
    {
        return $this->lastContentAt;
    }

    private function tickHeartbeat(): void
    {
        if ($this->closed) {
            return;
        }
        if ($this->ticker->now() - $this->lastContentAt >= $this->profile->idleSeconds()) {
            $this->close(StreamCloseReason::Idle);

            return;
        }
        $this->writer->heartbeat();
    }

    private function tickProbe(): void
    {
        if ($this->closed) {
            return;
        }
        $current = $this->probe->authzVersion($this->subject);
        if ($current === null || $current !== $this->authzVersion) {
            $this->close(StreamCloseReason::Revoked);
        }
    }
}
