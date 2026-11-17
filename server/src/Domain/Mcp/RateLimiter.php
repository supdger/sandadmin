<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 限流端口。MCP 按凭据、按工具两个维度各建一个桶（U-3/M-03）。
 * 仓库此前没有统一限流抽象，这里新立接口；实现放 Infra。
 */
interface RateLimiter
{
    /**
     * 在 windowSeconds 窗口内给 bucket 记一次命中。
     * 返回 false = 本窗口额度已用完，调用方应拒绝。
     */
    public function hit(string $bucket, int $limit, int $windowSeconds): bool;
}
