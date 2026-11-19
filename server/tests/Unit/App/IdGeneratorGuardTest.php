<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\App;

use app\bootstrap\IdGeneratorGuard;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Id\SnowflakeConfigException;
use Sandadmin\Infra\Id\SnowflakeRuntime;

/** 发号器启动自检：节点号不在部署号段内或必填项缺失即抛异常（启动期拒绝启动）。 */
final class IdGeneratorGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        SnowflakeRuntime::reset();
    }

    public function testValidConfigMountsGenerator(): void
    {
        IdGeneratorGuard::start(null, [
            'node_range' => '0-1023',
            'node_id' => 7,
            'max_clock_backwards_ms' => 10,
        ]);
        self::assertGreaterThan(0, SnowflakeRuntime::next());
    }

    public function testNodeOutsideRangeRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        $this->expectExceptionMessageMatches('/不在本部署号段/');
        IdGeneratorGuard::start(null, [
            'node_range' => '0-1023',
            'node_id' => 2000,
            'max_clock_backwards_ms' => 10,
        ]);
    }

    public function testMissingThresholdRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        IdGeneratorGuard::start(null, [
            'node_range' => '0-1023',
            'node_id' => 7,
        ]);
    }

    public function testMissingNodeRejectsStartup(): void
    {
        $this->expectException(SnowflakeConfigException::class);
        IdGeneratorGuard::start(null, [
            'node_range' => '0-1023',
            'max_clock_backwards_ms' => 10,
        ]);
    }
}
