<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 定时端口：心跳、权限复查、空闲与总时长上限都要定时器；
 * 生产实现包 Workerman\Timer，测试用手动拨表实现，不让单测依赖事件循环。
 * now() 与定时器共用一个钟，虚拟钟下定时语义自洽。
 */
interface StreamTicker
{
    /** 当前时间（秒）。 */
    public function now(): float;

    /** @param \Closure(): void $tick @return int 可传给 cancel() 的句柄 */
    public function every(float $seconds, \Closure $tick): int;

    /** @param \Closure(): void $tick 一次性定时 */
    public function after(float $seconds, \Closure $tick): int;

    public function cancel(int $timerId): void;
}
