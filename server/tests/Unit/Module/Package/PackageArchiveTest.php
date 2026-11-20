<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\PackageArchive;
use Sandadmin\Module\Package\PluginPackageManifest;
use Sandadmin\Tests\Support\FileKit;
use Sandadmin\Tests\Support\PackageKit;

/** 发行 zip 检查与解包：sha256、条目红线、载荷布局、manifest 与落盘。 */
final class PackageArchiveTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = FileKit::scratchRoot();
        if (!mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            self::fail('无法创建临时目录');
        }
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            FileKit::removeTree($this->dir);
            $this->dir = '';
        }
    }

    public function testFixtureZipInspectsClean(): void
    {
        $file = $this->zip('hello');
        PackageKit::fixtureZip($file, 'sand-hello');

        $seen = PackageArchive::inspect($file, null);

        self::assertSame([], $seen['problems']);
        self::assertSame('plugin/sand-hello/', $seen['payload']);
        self::assertSame('sand-hello', $seen['manifest']?->app);
    }

    public function testSha256MatchPasses(): void
    {
        $file = $this->zip('hello');
        PackageKit::fixtureZip($file, 'sand-hello');
        $digest = hash_file('sha256', $file);
        self::assertIsString($digest);

        $seen = PackageArchive::inspect($file, $digest);

        self::assertSame([], $seen['problems']);
    }

    public function testSha256MismatchReportsIntegrityProblem(): void
    {
        $file = $this->zip('hello');
        PackageKit::fixtureZip($file, 'sand-hello');

        $seen = PackageArchive::inspect($file, str_repeat('0', 64));

        self::assertNull($seen['manifest']);
        $text = implode("\n", $seen['problems']);
        self::assertStringContainsString('sha256', $text);
        // 只声称完整性校验没过，不出现验签字样
        self::assertStringNotContainsString('签名', $text);
    }

    public function testMissingManifestReportsProblem(): void
    {
        $file = $this->zip('nomanifest');
        PackageKit::makeZip($file, ['plugin/sand-hello/app/x.php' => '<?php']);

        $seen = PackageArchive::inspect($file, null);

        self::assertNull($seen['manifest']);
        self::assertNotEmpty($seen['problems']);
    }

    public function testBadManifestReportsProblems(): void
    {
        $file = $this->zip('badmanifest');
        PackageKit::fixtureZip($file, 'sand-hello', '{"app": "Bad!!"}');

        $seen = PackageArchive::inspect($file, null);

        self::assertNull($seen['manifest']);
        self::assertNotEmpty($seen['problems']);
    }

    public function testDotDotEntryRejected(): void
    {
        $this->assertRejected(['plugin/sand-hello/../evil.php' => 'x'], '..');
    }

    public function testAbsolutePathEntryRejected(): void
    {
        $this->assertRejected(['/abs/evil.php' => 'x'], '绝对路径');
    }

    public function testVendorEntryRejected(): void
    {
        $this->assertRejected(['plugin/sand-hello/vendor/pkg/a.php' => 'x'], 'vendor');
    }

    public function testNodeModulesEntryRejected(): void
    {
        $this->assertRejected(['web/node_modules/pkg/index.js' => 'x'], 'node_modules');
    }

    public function testBannedSqlEntryRejected(): void
    {
        $this->assertRejected(['plugin/sand-hello/install.sql' => 'select 1'], 'install.sql');
        $this->assertRejected(['update.sql' => 'select 1'], 'update.sql');
    }

    public function testEnvEntryRejected(): void
    {
        $this->assertRejected(['plugin/sand-hello/.env' => 'SECRET=1'], '.env');
    }

    public function testPharEntryRejected(): void
    {
        $this->assertRejected(['plugin/sand-hello/app.phar' => 'x'], '.phar');
    }

    public function testBothPayloadLayoutsRejected(): void
    {
        $file = $this->zip('both');
        PackageKit::fixtureZip($file, 'sand-hello', null, ['server/app/x.php' => '<?php']);

        $seen = PackageArchive::inspect($file, null);

        self::assertNull($seen['payload']);
        self::assertNotEmpty($seen['problems']);
    }

    public function testMissingPayloadRejected(): void
    {
        $file = $this->zip('empty');
        PackageKit::makeZip($file, ['manifest.json' => PackageKit::fixtureManifest('sand-hello')]);

        $seen = PackageArchive::inspect($file, null);

        self::assertNull($seen['payload']);
        self::assertNotEmpty($seen['problems']);
    }

    public function testPluginPrefixMustMatchManifestApp(): void
    {
        $file = $this->zip('mismatch');
        // zip 里是 plugin/sand-other/，清单写的是 sand-hello：两种载荷都不算
        $manifest = PackageKit::fixtureManifest('sand-hello');
        PackageKit::makeZip($file, [
            'manifest.json' => $manifest,
            'plugin/sand-other/app/x.php' => '<?php',
        ]);

        $seen = PackageArchive::inspect($file, null);

        self::assertNull($seen['payload']);
        self::assertNotEmpty($seen['problems']);
    }

    public function testServerPayloadAccepted(): void
    {
        $file = $this->zip('server');
        PackageKit::makeZip($file, [
            'manifest.json' => PackageKit::fixtureManifest('sand-hello'),
            'server/app/x.php' => '<?php',
            'server/contract/openapi.yaml' => 'openapi: 3.1.0',
        ]);

        $seen = PackageArchive::inspect($file, null);

        self::assertSame([], $seen['problems']);
        self::assertSame('server/', $seen['payload']);
    }

    public function testExtractWritesSubtreeAndRootManifest(): void
    {
        $file = $this->zip('hello');
        PackageKit::fixtureZip($file, 'sand-hello');
        $seen = PackageArchive::inspect($file, null);
        $manifest = $seen['manifest'];
        $payload = $seen['payload'];
        self::assertNotNull($manifest);
        self::assertNotNull($payload);

        $target = $this->dir . '/staged';
        PackageArchive::unpack($file, $payload, $target, $manifest);

        self::assertFileExists($target . '/manifest.json');
        self::assertFileExists($target . '/contract/openapi.yaml');
        self::assertFileExists($target . '/app/controller/NoteController.php');
        $written = file_get_contents($target . '/manifest.json');
        self::assertIsString($written);
        $parsed = PluginPackageManifest::parse($written);
        self::assertSame('sand-hello', $parsed['manifest']?->app);
    }

    private function zip(string $name): string
    {
        return $this->dir . '/' . $name . '.zip';
    }

    /** @param array<string, string> $extra */
    private function assertRejected(array $extra, string $needle): void
    {
        $file = $this->zip('rejected-' . bin2hex(random_bytes(4)));
        PackageKit::fixtureZip($file, 'sand-hello', null, $extra);

        $seen = PackageArchive::inspect($file, null);

        self::assertNull($seen['manifest']);
        self::assertNull($seen['payload']);
        self::assertStringContainsString($needle, implode("\n", $seen['problems']));
    }
}
