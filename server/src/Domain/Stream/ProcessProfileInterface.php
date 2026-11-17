<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 进程组形态声明（J-5 接口点名）：角色、每进程挂路数上限、超时档、
 * 回收策略。数值全部可配（config/stream.php）；单流总时长上限
 * 300 秒是硬顶（预计更长的改后台任务），实现不得放行更大值。
 */
interface ProcessProfileInterface
{
    /** 进程组角色标识：web / ai_stream / task / outbox 等。 */
    public function role(): string;

    /** 每进程同时挂的流式路数上限（初值 50–100，按压测定）。 */
    public function streamLimit(): int;

    /** 无内容事件空闲超时（秒）；上游 ping 心跳不算进度。 */
    public function idleSeconds(): int;

    /** 单流总时长上限（秒），实现钳制到 300 以内。 */
    public function streamSeconds(): int;

    /** SSE 心跳间隔（秒，J-5 定 15）。 */
    public function heartbeatSeconds(): int;

    /** 流式权限复查间隔（秒，J-5 定 30）。 */
    public function probeSeconds(): int;

    /** 发送缓冲触顶的取舍策略。 */
    public function backpressure(): BackpressurePolicy;

    /** 进程回收：处理请求数到量重启；0 表示不按请求数回收。 */
    public function maxRequests(): int;

    /** 进程回收：内存水位线（字节）到线重启；0 表示不按内存回收。 */
    public function memoryLimitBytes(): int;
}
