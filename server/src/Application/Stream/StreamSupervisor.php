<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Stream;

use Sandadmin\Domain\Stream\ProcessProfileInterface;
use Sandadmin\Domain\Stream\StreamCloseReason;
use Sandadmin\Domain\Stream\StreamLimitException;
use Sandadmin\Domain\Stream\StreamMetrics;
use Sandadmin\Domain\Stream\StreamPermissionProbe;
use Sandadmin\Domain\Stream\StreamTicker;
use Sandadmin\Domain\Stream\StreamWriterInterface;

/**
 * 每进程一路的流式账本：按 ProcessProfileInterface 限路（超限抛
 * StreamLimitException = 429 STREAM_LIMIT_REACHED）、撤权即断开
 * 该用户全部流式连接（revokeSubject）、按 StreamCloseReason 计数。
 * 会话表是进程级共享状态——「每进程挂路数」口径本来就按进程算（J-5），
 * 本类不装任何请求态数据。
 */
final class StreamSupervisor
{
    /** @var array<int, StreamSession> */
    private array $sessions = [];

    private int $nextId = 1;

    public function __construct(
        private readonly ProcessProfileInterface $profile,
        private readonly StreamTicker $ticker,
        private readonly StreamMetrics $metrics,
    ) {
    }

    public function profile(): ProcessProfileInterface
    {
        return $this->profile;
    }

    /**
     * 开一路流。调用方随后 start() 会话并自行把生产方（定时器、通道订阅）
     * 挂到会话上；关流时经 addCloseListener 反挂生产方资源。
     *
     * @throws StreamLimitException 本进程挂路数已达上限
     */
    public function open(
        string $subject,
        int $authzVersion,
        StreamWriterInterface $writer,
        StreamPermissionProbe $probe,
    ): StreamSession {
        if ($this->activeCount() >= $this->profile->streamLimit()) {
            throw new StreamLimitException();
        }
        $session = new StreamSession(
            $this->nextId++,
            $subject,
            $writer,
            $this->profile,
            $this->ticker,
            $probe,
            $authzVersion,
        );
        $session->addCloseListener(function (StreamCloseReason $reason) use ($session): void {
            unset($this->sessions[$session->id()]);
            $this->metrics->streamClosed($reason->value);
        });
        $this->sessions[$session->id()] = $session;
        $this->metrics->streamOpened();

        return $session;
    }

    /** 撤权即断开该主体全部流式连接；返回断开的路数。 */
    public function revokeSubject(string $subject): int
    {
        $count = 0;
        foreach ($this->sessions as $session) {
            if ($session->subject() === $subject) {
                $session->close(StreamCloseReason::Revoked);
                $count++;
            }
        }

        return $count;
    }

    public function activeCount(): int
    {
        return count($this->sessions);
    }

    /** @return list<StreamSession> */
    public function sessions(): array
    {
        return array_values($this->sessions);
    }
}
