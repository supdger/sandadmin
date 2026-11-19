<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Id;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Id\SnowflakeConfig;
use Sandadmin\Infra\Id\SnowflakeConfigException;

/** 发号器节点配置：号段解析、必填项与越界校验。 */
final class SnowflakeConfigTest extends TestCase
{
    public function testArrayFormAccepted(): void
    {
        $config = SnowflakeConfig::fromArray([
            'node_range' => [100, 199],
            'node_id' => 150,
            'max_clock_backwards_ms' => 10,
        ]);
        self::assertSame(150, $config->nodeBase);
        self::assertSame(100, $config->rangeStart);
        self::assertSame(199, $config->rangeEnd);
        self::assertSame(10, $config->maxBackwardsMs);
    }

    public function testStringRangeAccepted(): void
    {
        $config = SnowflakeConfig::fromArray([
            'node_range' => '0-1023',
            'node_id' => '42',
            'max_clock_backwards_ms' => '5',
        ]);
        self::assertSame(0, $config->rangeStart);
        self::assertSame(1023, $config->rangeEnd);
        self::assertSame(42, $config->nodeBase);
    }

    public function testNodeOutsideRangeRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/不在本部署号段/');
        SnowflakeConfig::fromArray([
            'node_range' => '0-1023',
            'node_id' => 2000,
            'max_clock_backwards_ms' => 10,
        ]);
    }

    public function testMissingThresholdRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        SnowflakeConfig::fromArray([
            'node_range' => '0-1023',
            'node_id' => 1,
        ]);
    }

    public function testNonPositiveThresholdRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        SnowflakeConfig::fromArray([
            'node_range' => '0-1023',
            'node_id' => 1,
            'max_clock_backwards_ms' => 0,
        ]);
    }

    public function testInvertedRangeRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        SnowflakeConfig::fromArray([
            'node_range' => '500-100',
            'node_id' => 300,
            'max_clock_backwards_ms' => 10,
        ]);
    }

    public function testRangeBeyondNodeMaxRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        SnowflakeConfig::fromArray([
            'node_range' => '0-9000',
            'node_id' => 1,
            'max_clock_backwards_ms' => 10,
        ]);
    }

    public function testEmptyEnvValuesRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        SnowflakeConfig::fromArray([
            'node_range' => '',
            'node_id' => '',
            'max_clock_backwards_ms' => '',
        ]);
    }

    public function testWorkerTotalWithinRangeAccepted(): void
    {
        $config = SnowflakeConfig::fromArray([
            'node_range' => '0-1023',
            'node_id' => 1000,
            'max_clock_backwards_ms' => 10,
        ], 24);
        self::assertSame(1000, $config->nodeBase);
    }

    public function testWorkerTotalBeyondRangeRejected(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/超出号段上限/');
        SnowflakeConfig::fromArray([
            'node_range' => '0-1023',
            'node_id' => 1000,
            'max_clock_backwards_ms' => 10,
        ], 25);
    }
}
