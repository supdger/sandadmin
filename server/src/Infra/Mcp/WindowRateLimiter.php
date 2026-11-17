<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp;

use Sandadmin\Domain\Mcp\RateLimiter;

/**
 * 进程内固定窗口限流：每个 worker 独立计数。
 * 开发构建单机调用足够；正式机器令牌上线时换共享存储实现
 * （多 worker 计数合并），接口不变。窗口内超额的桶按 key 常驻到窗口结束。
 */
final class WindowRateLimiter implements RateLimiter
{
    /** @var array<string, array{start: int, hits: int}> */
    private array $buckets = [];

    /** @param null|\Closure(): int $now 测试注入时钟，秒 */
    public function __construct(private readonly ?\Closure $now = null)
    {
    }

    public function hit(string $bucket, int $limit, int $windowSeconds): bool
    {
        if ($limit <= 0 || $windowSeconds <= 0) {
            return false;
        }
        $at = $this->now();
        $slot = $this->buckets[$bucket] ?? null;
        if ($slot === null || $at - $slot['start'] >= $windowSeconds) {
            $slot = ['start' => $at, 'hits' => 0];
            $this->buckets[$bucket] = $slot;
        }
        if ($slot['hits'] >= $limit) {
            return false;
        }
        $this->buckets[$bucket]['hits']++;
        // 防止长跑进程里桶无限堆积：顺手清掉过期窗口
        if (count($this->buckets) > 512) {
            foreach ($this->buckets as $key => $item) {
                if ($at - $item['start'] >= $windowSeconds) {
                    unset($this->buckets[$key]);
                }
            }
        }

        return true;
    }

    private function now(): int
    {
        if ($this->now === null) {
            return time();
        }

        return ($this->now)();
    }
}
