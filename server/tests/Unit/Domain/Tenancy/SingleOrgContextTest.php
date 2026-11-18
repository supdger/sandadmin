<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Tenancy;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Tenancy\SingleOrgContext;

final class SingleOrgContextTest extends TestCase
{
    public function testReturnsFixedValuesWhenOrgEnabled(): void
    {
        $context = new SingleOrgContext();

        self::assertSame(SingleOrgContext::DEFAULT_ORG_ID, $context->orgId());
        self::assertSame('default', $context->shardKey());
        self::assertSame('default:', $context->cachePrefix());
        self::assertSame('default/', $context->storagePrefix());
    }

    public function testOrgIdIsNullWhenOrgNotEnabled(): void
    {
        $context = new SingleOrgContext(null);

        self::assertNull($context->orgId());
        // 未启用组织时部署仍只有一个逻辑分片，分片键与前缀不变
        self::assertSame('default', $context->shardKey());
        self::assertSame('default:', $context->cachePrefix());
        self::assertSame('default/', $context->storagePrefix());
    }

    public function testAcceptsExplicitFixedOrgId(): void
    {
        $context = new SingleOrgContext(7);

        self::assertSame(7, $context->orgId());
    }

    public function testRejectsNonPositiveOrgId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SingleOrgContext(0);
    }
}
