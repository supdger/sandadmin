<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Http;

/**
 * 出站追踪上下文解析端口。resolve() 只在存在合法入站 traceparent 时
 * 返回续接上下文，否则返回 null 由调用方生成；requestId() 提供出站
 * X-Request-Id，无入站上下文（Task/CLI）时自行生成新 id。
 */
interface TraceContextResolver
{
    public function resolve(): ?TraceContext;

    public function requestId(): string;
}
