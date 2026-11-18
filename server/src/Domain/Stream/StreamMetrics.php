<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 指标与链路追踪钩子：当前路数（opened − closed）、断开原因计数、
 * 背压丢事件与缓冲触顶计数。仓内暂无统一指标接口，先立窄端口；
 * 接监控系统时换实现，调用方不动。reason 取 StreamCloseReason 值。
 */
interface StreamMetrics
{
    public function streamOpened(): void;

    public function streamClosed(string $reason): void;

    public function eventDropped(string $reason): void;

    public function bufferFull(): void;
}
