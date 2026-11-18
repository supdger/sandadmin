<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Tenancy;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Tenancy\DefaultShardResolver;
use Sandadmin\Domain\Tenancy\ShardResolverInterface;
use Sandadmin\Domain\Tenancy\SingleOrgContext;
use Sandadmin\Domain\Tenancy\SingleRequestContext;

final class DefaultShardResolverTest extends TestCase
{
    public function testShardKeyOfReturnsDefaultShard(): void
    {
        $resolver = new DefaultShardResolver();
        $context = new SingleRequestContext(new SingleOrgContext(), 'trace-12345678');

        self::assertSame(ShardResolverInterface::DEFAULT_SHARD_KEY, $resolver->shardKeyOf($context));
    }

    public function testConnectionNameOfReturnsDefaultConnection(): void
    {
        $resolver = new DefaultShardResolver();

        self::assertSame(
            ShardResolverInterface::DEFAULT_CONNECTION,
            $resolver->connectionNameOf(ShardResolverInterface::DEFAULT_SHARD_KEY),
        );
    }

    public function testConnectionNameOfRejectsUnknownShardKey(): void
    {
        $resolver = new DefaultShardResolver();

        $this->expectException(\InvalidArgumentException::class);
        $resolver->connectionNameOf('org:7');
    }

    public function testBroadcastConnectionsIsTheExplicitBroadcastPath(): void
    {
        $resolver = new DefaultShardResolver();

        self::assertSame([ShardResolverInterface::DEFAULT_CONNECTION], $resolver->broadcastConnections());
    }
}
