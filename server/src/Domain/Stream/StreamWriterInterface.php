<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 单路流式写出的端口（J-5 接口点名）。插件与上层只面向本接口，
 * 不直连 workerman 原语；SSE 实现在 Infra（SseStreamWriter），
 * 非流式/缓冲路径用 MemoryStreamWriter。
 * send/heartbeat 返回 false = 事件被背压策略丢弃或对端已断开，
 * 调用方按 isPaused()/isClosed() 决定是否停手。
 */
interface StreamWriterInterface
{
    /** 写一条事件；缓冲触顶按构造时定下的 BackpressurePolicy 处理。 */
    public function send(StreamEvent $event): bool;

    /** 写一行 SSE 注释心跳（防中间代理按空闲断连，J-5 每 15 秒一行）。 */
    public function heartbeat(): bool;

    /** 先发一个 error 事件再结束连接；code 必须是已登记的契约错误码。 */
    public function fail(string $code, string $message): void;

    /** 正常结束。已关闭再调用是空操作。 */
    public function close(): void;

    public function isClosed(): bool;

    /** 发送缓冲触顶处于暂停/丢弃窗口；Pause 策略下生产者应停止生成。 */
    public function isPaused(): bool;

    /** @param \Closure(): void $gone 客户端断开或底层写出失败时触发，至多一次 */
    public function whenClientGone(\Closure $gone): void;
}
