<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 跨进程扇出的发布/订阅端口（J-5 接口点名）：任务进程写增量事件，
 * 流式进程只订阅扇出，稳态不查库。事件的 id 字段兼作断线补发游标；
 * 断线重连按 Last-Event-ID 走 StreamReplayStore 补发。
 */
interface StreamChannelInterface
{
    public function publish(string $channel, StreamEvent $event): void;

    /** @param \Closure(StreamEvent): void $onEvent */
    public function subscribe(string $channel, \Closure $onEvent): void;

    public function unsubscribe(string $channel): void;
}
