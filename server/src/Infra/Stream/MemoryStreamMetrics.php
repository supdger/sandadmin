<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\StreamMetrics;

/**
 * 进程内计数实现：单测断言与开发期本地观测用。
 * 多进程部署的总账需要共享存储出口（仓内暂无统一指标接口，
 * 接监控系统时换实现）；每进程挂路数这类本进程口径的指标用它就是准的。
 */
final class MemoryStreamMetrics implements StreamMetrics
{
    private int $opened = 0;

    /** @var array<string, int> */
    private array $closedByReason = [];

    /** @var array<string, int> */
    private array $droppedByReason = [];

    private int $bufferFullCount = 0;

    public function streamOpened(): void
    {
        $this->opened++;
    }

    public function streamClosed(string $reason): void
    {
        $this->closedByReason[$reason] = ($this->closedByReason[$reason] ?? 0) + 1;
    }

    public function eventDropped(string $reason): void
    {
        $this->droppedByReason[$reason] = ($this->droppedByReason[$reason] ?? 0) + 1;
    }

    public function bufferFull(): void
    {
        $this->bufferFullCount++;
    }

    /** 当前挂着的流式路数。 */
    public function activeCount(): int
    {
        $closed = 0;
        foreach ($this->closedByReason as $count) {
            $closed += $count;
        }

        return $this->opened - $closed;
    }

    /**
     * @return array{opened: int, active: int, closed: array<string, int>, dropped: array<string, int>, buffer_full: int}
     */
    public function counts(): array
    {
        return [
            'opened' => $this->opened,
            'active' => $this->activeCount(),
            'closed' => $this->closedByReason,
            'dropped' => $this->droppedByReason,
            'buffer_full' => $this->bufferFullCount,
        ];
    }
}
