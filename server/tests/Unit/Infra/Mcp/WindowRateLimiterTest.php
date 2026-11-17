<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Mcp\WindowRateLimiter;

/** 固定窗口限流：凭据桶与工具桶独立计数，窗口过期后重置。 */
final class WindowRateLimiterTest extends TestCase
{
    public function testCredentialBucketExhausts(): void
    {
        $now = 1000;
        $limiter = new WindowRateLimiter(static function () use (&$now): int {
            return $now;
        });

        self::assertTrue($limiter->hit('mcp:credential:dev-token', 2, 60));
        self::assertTrue($limiter->hit('mcp:credential:dev-token', 2, 60));
        self::assertFalse($limiter->hit('mcp:credential:dev-token', 2, 60));
    }

    public function testToolBucketsAreIndependent(): void
    {
        $limiter = new WindowRateLimiter();
        $bucketA = 'mcp:tool:dev-token:a';
        $bucketB = 'mcp:tool:dev-token:b';

        self::assertTrue($limiter->hit($bucketA, 1, 60));
        self::assertFalse($limiter->hit($bucketA, 1, 60));
        self::assertTrue($limiter->hit($bucketB, 1, 60));
    }

    public function testWindowReset(): void
    {
        $now = 1000;
        $limiter = new WindowRateLimiter(static function () use (&$now): int {
            return $now;
        });

        self::assertTrue($limiter->hit('b', 1, 10));
        self::assertFalse($limiter->hit('b', 1, 10));
        $now += 11;
        self::assertTrue($limiter->hit('b', 1, 10));
    }

    public function testInvalidSettingsDeny(): void
    {
        $limiter = new WindowRateLimiter();

        self::assertFalse($limiter->hit('b', 0, 60));
        self::assertFalse($limiter->hit('b', 1, 0));
    }
}
