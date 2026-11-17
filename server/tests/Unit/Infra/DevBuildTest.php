<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra;

use app\bootstrap\ProductionDebugGuard;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\DevBuild;

/** 开发构建标记：本检出是开发构建；生产环境禁带标记与 SAND_DEV_LOGIN。 */
final class DevBuildTest extends TestCase
{
    public function testEnabledInDevCheckout(): void
    {
        self::assertTrue(DevBuild::enabled());
        self::assertFileExists(dirname(__DIR__, 3) . '/.sand-dev-build');
    }

    public function testProductionGuardRejectsDevBuild(): void
    {
        $this->expectException(\RuntimeException::class);
        ProductionDebugGuard::assertDevChainOff('production', true, null);
    }

    public function testProductionGuardRejectsDevLoginEnv(): void
    {
        $this->expectException(\RuntimeException::class);
        ProductionDebugGuard::assertDevChainOff('production', false, '1');
    }

    public function testProductionGuardRejectsMcpDevToken(): void
    {
        $this->expectException(\RuntimeException::class);
        ProductionDebugGuard::assertDevChainOff('production', false, null, 'any-token');
    }

    public function testProductionGuardPassesCleanProd(): void
    {
        ProductionDebugGuard::assertDevChainOff('production', false, null);
        ProductionDebugGuard::assertDevChainOff('production', false, '0');
        ProductionDebugGuard::assertDevChainOff('production', false, null, '');
        ProductionDebugGuard::assertDevChainOff('production', false, null, false);
        $this->addToAssertionCount(1);
    }

    public function testGuardSkipsNonProduction(): void
    {
        ProductionDebugGuard::assertDevChainOff('development', true, '1');
        ProductionDebugGuard::assertDevChainOff('development', false, null, 'any-token');
        $this->addToAssertionCount(1);
    }
}
