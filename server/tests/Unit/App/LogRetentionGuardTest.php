<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\App;

use app\bootstrap\LogRetentionGuard;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Log\LogRetentionException;

/** 日志留存启动守卫：通道 days 低于类别下限即抛异常（启动期拒绝启动）。 */
final class LogRetentionGuardTest extends TestCase
{
    public function testValidConfigPasses(): void
    {
        $this->expectNotToPerformAssertions();
        LogRetentionGuard::start(null, [
            'default' => ['retention' => ['kind' => 'general', 'days' => 183]],
            'security' => ['retention' => ['kind' => 'security', 'days' => 183]],
        ]);
    }

    public function testBelowMinRejectsStartup(): void
    {
        $this->expectException(LogRetentionException::class);
        $this->expectExceptionMessageMatches('/security.*30.*183/');
        LogRetentionGuard::start(null, [
            'security' => ['retention' => ['kind' => 'security', 'days' => 30]],
        ]);
    }

    public function testEmptyConfigPasses(): void
    {
        $this->expectNotToPerformAssertions();
        LogRetentionGuard::start(null, []);
    }
}
