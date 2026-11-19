<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\PackageGate;

/** 网页安装开关：环境变量矩阵，测后原样恢复。 */
final class PackageGateTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (['APP_ENV', 'SAND_COMPILED', 'SAND_PACKAGE_OPS'] as $name) {
            $this->envBackup[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }

    private function env(?string $appEnv, ?string $compiled, ?string $ops): void
    {
        foreach (['APP_ENV' => $appEnv, 'SAND_COMPILED' => $compiled, 'SAND_PACKAGE_OPS' => $ops] as $name => $value) {
            if ($value === null) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
    }

    public function testProductionOnlyWhenEnvSaysSo(): void
    {
        $this->env('production', null, null);
        self::assertTrue(PackageGate::production());
        $this->env('local', null, null);
        self::assertFalse(PackageGate::production());
        $this->env(null, null, null);
        self::assertFalse(PackageGate::production());
    }

    public function testCompiledDefaultsTrue(): void
    {
        $this->env('local', null, null);
        self::assertTrue(PackageGate::compiled(), '未设置 SAND_COMPILED 按编译版处理');
        $this->env('local', '', null);
        self::assertTrue(PackageGate::compiled(), '空串同样按编译版');
        $this->env('local', '1', null);
        self::assertTrue(PackageGate::compiled());
        $this->env('local', '0', null);
        self::assertFalse(PackageGate::compiled());
    }

    public function testProductionImpliesCompiled(): void
    {
        $this->env('production', '0', '1');
        self::assertTrue(PackageGate::compiled(), '生产环境无视 SAND_COMPILED=0');
    }

    public function testOpsEnabledRequiresCompiledOffAndOpsFlag(): void
    {
        $this->env('local', '0', '1');
        self::assertTrue(PackageGate::opsEnabled());
        $this->env('local', '0', '0');
        self::assertFalse(PackageGate::opsEnabled());
        $this->env('local', '0', null);
        self::assertFalse(PackageGate::opsEnabled());
        $this->env('local', '1', '1');
        self::assertFalse(PackageGate::opsEnabled(), '编译版下 ops 开关无效');
        $this->env('production', '0', '1');
        self::assertFalse(PackageGate::opsEnabled());
    }
}
