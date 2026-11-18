<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Tenancy;

/**
 * 1.0 分片解析默认实现：只有一个 'default' 分片、一个默认连接，
 * 不做真实分库路由。未识别的分片键说明调用方路由错位，
 * 抛异常拒绝，不静默指到默认连接。
 */
final class DefaultShardResolver implements ShardResolverInterface
{
    public function shardKeyOf(RequestContextInterface $ctx): string
    {
        return $ctx->org()->shardKey();
    }

    public function connectionNameOf(string $shardKey): string
    {
        if ($shardKey !== self::DEFAULT_SHARD_KEY) {
            throw new \InvalidArgumentException("未识别的分片键：{$shardKey}");
        }

        return self::DEFAULT_CONNECTION;
    }

    public function broadcastConnections(): array
    {
        return [self::DEFAULT_CONNECTION];
    }
}
