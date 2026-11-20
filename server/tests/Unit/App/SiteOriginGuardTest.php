<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\App;

use app\bootstrap\SiteOriginGuard;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/** A-5 启动守卫：生产环境缺 security.site_origin 即拒绝启动，开发环境放行。 */
final class SiteOriginGuardTest extends TestCase
{
    private const SITE = 'https://admin.example.com';

    public function testProductionWithoutSiteOriginRejectsStartup(): void
    {
        $halted = false;
        try {
            $this->runStart('production', [], $halted);
            self::fail('生产环境缺 site_origin 未拒绝启动');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('site_origin', $error->getMessage());
        }
        self::assertTrue($halted);
    }

    public function testProdAliasAlsoRejects(): void
    {
        $halted = false;
        try {
            $this->runStart('prod', [], $halted);
            self::fail('APP_ENV=prod 缺 site_origin 未拒绝启动');
        } catch (RuntimeException) {
        }
        self::assertTrue($halted);
    }

    public function testProductionWithSiteOriginPasses(): void
    {
        $halted = false;
        $this->runStart('production', ['site_origin' => self::SITE], $halted);
        self::assertFalse($halted);
    }

    public function testDevelopmentWithoutSiteOriginPasses(): void
    {
        // 开发期保持原行为：未配置跳过源校验，不拦启动
        $halted = false;
        $this->runStart('development', [], $halted);
        self::assertFalse($halted);
    }

    public function testMissingAppEnvWithoutSiteOriginPasses(): void
    {
        $halted = false;
        $this->runStart(null, [], $halted);
        self::assertFalse($halted);
    }

    public function testMalformedSiteOriginRejectsStartup(): void
    {
        // 形态非法与未配置一样拒启（CsrfConfig 抛 InvalidArgumentException），与环境无关
        $halted = false;
        try {
            $this->runStart('development', ['site_origin' => 'https://admin.example.com/dashboard'], $halted);
            self::fail('非法 site_origin 未拒绝启动');
        } catch (InvalidArgumentException) {
        }
        self::assertTrue($halted);
    }

    public function testRegisteredInBootstrap(): void
    {
        $boot = require dirname(__DIR__, 3) . '/config/bootstrap.php';
        self::assertIsArray($boot);
        self::assertContains(SiteOriginGuard::class, $boot);
    }

    /**
     * @param array<array-key, mixed> $securityConfig
     * @param-out bool $halted
     */
    private function runStart(?string $appEnv, array $securityConfig, bool &$halted): void
    {
        if ($appEnv === null) {
            putenv('APP_ENV');
        } else {
            putenv("APP_ENV={$appEnv}");
        }
        try {
            SiteOriginGuard::start(
                new stdClass(),
                $securityConfig,
                static function (object $worker) use (&$halted): void {
                    $halted = true;
                },
            );
        } finally {
            putenv('APP_ENV');
        }
    }
}
