<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\App;

use app\support\DevLoginGate;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\DevBuild;

/**
 * 无开发构建标记时免登录整条链关闭。
 * setUpBeforeClass 在父进程置 SAND_TEST_DEV_BUILD=0，子进程 bootstrap
 * 据此跳过常量定义，模拟生产制品（常量一旦定义无法撤销，必须进程隔离）。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DevBuildOffTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        putenv('SAND_TEST_DEV_BUILD=0');
    }

    public static function tearDownAfterClass(): void
    {
        putenv('SAND_TEST_DEV_BUILD');
        putenv('SAND_DEV_LOGIN');
    }

    public function testGateClosedWithoutDevBuild(): void
    {
        self::assertFalse(DevBuild::enabled());
        putenv('SAND_DEV_LOGIN=1');
        self::assertFalse(DevLoginGate::enabled());
        self::assertFalse(DevLoginGate::allows(DevLoginGate::enabled(), '127.0.0.1', false));
    }
}
