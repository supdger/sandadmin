<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\Tenancy;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Tenancy\SingleOrgContext;
use Sandadmin\Domain\Tenancy\SingleRequestContext;

final class SingleRequestContextTest extends TestCase
{
    public function testExposesOrgContextAndTraceId(): void
    {
        $org = new SingleOrgContext();
        $context = new SingleRequestContext($org, 'trace-12345678');

        self::assertSame($org, $context->org());
        self::assertSame('trace-12345678', $context->traceId());
    }

    public function testRejectsEmptyTraceId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SingleRequestContext(new SingleOrgContext(), '');
    }

    public function testOrgAccessFailsAfterClear(): void
    {
        $context = new SingleRequestContext(new SingleOrgContext(), 'trace-abcdef12');

        $context->clear();

        $this->expectException(\LogicException::class);
        $context->org();
    }

    public function testTraceIdStaysReadableAfterClear(): void
    {
        $context = new SingleRequestContext(new SingleOrgContext(), 'trace-abcdef12');

        $context->clear();

        self::assertSame('trace-abcdef12', $context->traceId());
    }
}
