<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\StreamEvent;
use Sandadmin\Domain\Stream\StreamReplayStore;

/**
 * 内存版断线补发存储：每通道环形保留最近 perChannelLimit 条。
 * 只覆盖本进程见过的事件——多进程部署的完整补发要库表实现，
 * 库表等编号表定稿后另做，接口不变。
 */
final class MemoryReplayStore implements StreamReplayStore
{
    /** @var array<string, list<StreamEvent>> */
    private array $events = [];

    /** @var array<string, int> */
    private array $sequences = [];

    public function __construct(private readonly int $perChannelLimit = 1000)
    {
    }

    public function append(string $channel, StreamEvent $event): void
    {
        if ($event->id === null) {
            $sequence = ($this->sequences[$channel] ?? 0) + 1;
            $this->sequences[$channel] = $sequence;
            $event = new StreamEvent($channel . ':' . $sequence, $event->name, $event->data);
        }
        $this->events[$channel] ??= [];
        $this->events[$channel][] = $event;
        while (count($this->events[$channel]) > $this->perChannelLimit) {
            array_shift($this->events[$channel]);
        }
    }

    public function after(string $channel, ?string $cursor): array
    {
        $events = $this->events[$channel] ?? [];
        if ($cursor === null) {
            return $events;
        }
        foreach ($events as $index => $event) {
            if ($event->id === $cursor) {
                return array_slice($events, $index + 1);
            }
        }

        return $events;
    }
}
