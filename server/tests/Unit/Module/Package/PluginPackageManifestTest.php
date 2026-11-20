<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\PluginPackageManifest;
use Sandadmin\Tests\Support\PackageKit;

/** manifest.json 的逐字段校验：问题攒清单不抛异常，有问题就不给 manifest。 */
final class PluginPackageManifestTest extends TestCase
{
    public function testFixtureManifestParses(): void
    {
        $raw = file_get_contents(PackageKit::fixtureDir('sand-hello') . '/manifest.json');
        self::assertIsString($raw);
        $parsed = PluginPackageManifest::parse($raw);

        self::assertSame([], $parsed['problems']);
        $manifest = $parsed['manifest'];
        self::assertNotNull($manifest);
        self::assertSame('sand-hello', $manifest->app);
        self::assertSame('0.1.0', $manifest->version);
        self::assertSame('1.0.0', $manifest->kernelApiMin);
        self::assertSame('1.9.9', $manifest->kernelApiMax);
        self::assertSame(['pgsql', 'mysql'], $manifest->databases);
        self::assertSame([], $manifest->requires);
    }

    public function testInvalidJsonReportsProblem(): void
    {
        $parsed = PluginPackageManifest::parse('{broken');

        self::assertNull($parsed['manifest']);
        self::assertNotEmpty($parsed['problems']);
    }

    public function testBadAppReportsProblem(): void
    {
        $parsed = PluginPackageManifest::parse($this->manifest(['app' => 'Sand_Hello']));

        self::assertNull($parsed['manifest']);
        self::assertNotEmpty($parsed['problems']);
    }

    public function testMissingKernelBoundsReportProblems(): void
    {
        $parsed = PluginPackageManifest::parse('{"app": "sand-hello", "version": "0.1.0", "databases": ["pgsql"]}');

        self::assertNull($parsed['manifest']);
        $text = implode("\n", $parsed['problems']);
        self::assertStringContainsString('kernel_api_min', $text);
        self::assertStringContainsString('kernel_api_max', $text);
    }

    public function testCrossMajorRangeReportsProblem(): void
    {
        $parsed = PluginPackageManifest::parse($this->manifest([
            'kernel_api_min' => '1.0.0',
            'kernel_api_max' => '2.0.0',
        ]));

        self::assertNull($parsed['manifest']);
        self::assertNotEmpty($parsed['problems']);
    }

    public function testInvertedRangeReportsProblem(): void
    {
        $parsed = PluginPackageManifest::parse($this->manifest([
            'kernel_api_min' => '1.9.9',
            'kernel_api_max' => '1.0.0',
        ]));

        self::assertNull($parsed['manifest']);
        self::assertNotEmpty($parsed['problems']);
    }

    public function testEmptyDatabasesReportsProblem(): void
    {
        $parsed = PluginPackageManifest::parse($this->manifest(['databases' => []]));

        self::assertNull($parsed['manifest']);
        self::assertNotEmpty($parsed['problems']);
    }

    public function testUnknownDatabaseReportsProblem(): void
    {
        $parsed = PluginPackageManifest::parse($this->manifest(['databases' => ['sqlite']]));

        self::assertNull($parsed['manifest']);
        self::assertNotEmpty($parsed['problems']);
    }

    public function testValidRequiresParses(): void
    {
        $parsed = PluginPackageManifest::parse($this->manifest([
            'requires' => ['sand-iam' => '0.8.0,0.9.9'],
        ]));

        self::assertSame([], $parsed['problems']);
        self::assertSame(['sand-iam' => '0.8.0,0.9.9'], $parsed['manifest']?->requires);
    }

    public function testBadRequiresRangeReportsProblem(): void
    {
        $parsed = PluginPackageManifest::parse($this->manifest([
            'requires' => ['sand-iam' => 'not-a-range'],
        ]));

        self::assertNull($parsed['manifest']);
        self::assertNotEmpty($parsed['problems']);
    }

    /** @param array<string, mixed> $overrides */
    private function manifest(array $overrides): string
    {
        return PackageKit::fixtureManifest('sand-hello', $overrides);
    }
}
