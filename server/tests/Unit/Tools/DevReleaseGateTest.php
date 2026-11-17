<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;

$root = dirname(__DIR__, 4);
require_once $root . '/tools/dev-manifest.php';
require_once $root . '/tools/check-dev-refs.php';

/** 发布包 dev 剔除：清单解析、哨兵剔除与引用扫描的行为契约。 */
final class DevReleaseGateTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/devgate-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file instanceof \SplFileInfo && ($file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()));
        }
        rmdir($this->dir);
    }

    /** @return array{rm: list<string>, strip: list<string>} */
    private function manifest(string $body): array
    {
        $path = $this->dir . '/dev-only-paths.txt';
        file_put_contents($path, $body);

        return parseDevManifest($path);
    }

    public function testParseManifestSkipsCommentsAndBlankLines(): void
    {
        $parsed = $this->manifest("# 注释\n\nrm server/tests\nstrip server/config/route.php\n");
        self::assertSame(['server/tests'], $parsed['rm']);
        self::assertSame(['server/config/route.php'], $parsed['strip']);
    }

    public function testParseManifestRejectsIllegalLine(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->manifest("delete server/tests\n");
    }

    public function testParseManifestRejectsUnsafePath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->manifest("rm ../outside.php\n");
    }

    public function testMatchPathCoversFileDirAndGlob(): void
    {
        self::assertTrue(matchDevPath('server/tests', 'server/tests/bootstrap.php'));
        self::assertTrue(matchDevPath('server/tests', 'server/tests'));
        self::assertFalse(matchDevPath('server/tests', 'server/testsuite/x.php'));
        self::assertTrue(matchDevPath('web/src/**/*.test.ts', 'web/src/a/b/x.test.ts'));
        self::assertFalse(matchDevPath('web/src/*.test.ts', 'web/src/a/b/x.test.ts'));
        self::assertTrue(matchDevPath('web/src/*.test.ts', 'web/src/x.test.ts'));
    }

    public function testStripDevBlocksRemovesMarkedRanges(): void
    {
        $source = "keep1\n// SAND-DEV-BEGIN\ndev line\n// SAND-DEV-END\nkeep2\n";
        self::assertSame("keep1\nkeep2\n", stripDevBlocks($source, 'x.php'));
    }

    public function testStripDevBlocksRejectsUnbalanced(): void
    {
        $this->expectException(\RuntimeException::class);
        stripDevBlocks("a\n// SAND-DEV-END\n", 'x.php');
    }

    public function testStripDevBlocksRejectsNested(): void
    {
        $this->expectException(\RuntimeException::class);
        stripDevBlocks("// SAND-DEV-BEGIN\n// SAND-DEV-BEGIN\n// SAND-DEV-END\n// SAND-DEV-END\n", 'x.php');
    }

    public function testStripDevBlocksRequiresBlockInStrictMode(): void
    {
        self::assertSame('plain', stripDevBlocks('plain', 'x.php', false));
        $this->expectException(\RuntimeException::class);
        stripDevBlocks('plain', 'x.php');
    }

    public function testScanFlagsUnregisteredDevRef(): void
    {
        $files = ['a/Prod.php'];
        $manifest = ['rm' => [], 'strip' => []];
        $read = static fn (string $path): string => "use Sandadmin\\Http\\DevPrincipal;\n";
        self::assertNotSame([], scanDevRefs($files, $manifest, $read));
    }

    public function testScanPassesCleanAndRmFiles(): void
    {
        $files = ['a/Prod.php', 'server/src/Http/DevPrincipal.php'];
        $manifest = ['rm' => ['server/src/Http/DevPrincipal.php'], 'strip' => []];
        $read = static fn (string $path): string => "use Sandadmin\\Http\\DevPrincipal;\n";
        self::assertSame(['a/Prod.php:DevPrincipal@1'], scanDevRefs($files, $manifest, $read));
    }

    public function testScanChecksStripFileAfterBlockRemoval(): void
    {
        $manifest = ['rm' => [], 'strip' => ['route.php']];
        $inside = "// SAND-DEV-BEGIN\nuse DevPrincipal;\n// SAND-DEV-END\nkeep\n";
        $outside = "// SAND-DEV-BEGIN\nuse DevPrincipal;\n// SAND-DEV-END\nuse DevLoginGate;\n";
        self::assertSame(
            [],
            scanDevRefs(['route.php'], $manifest, static fn (): string => $inside),
        );
        self::assertSame(
            ['route.php:DevLoginGate@1'],
            scanDevRefs(['route.php'], $manifest, static fn (): string => $outside),
        );
    }

    public function testScanSkipsWhitelistedFiles(): void
    {
        $files = ['server/app/bootstrap/ProductionDebugGuard.php'];
        $manifest = ['rm' => [], 'strip' => []];
        $read = static fn (string $path): string => "DevBuild::enabled();\n";
        self::assertSame([], scanDevRefs($files, $manifest, $read));
    }

    public function testRealManifestAppliesCleanly(): void
    {
        $root = dirname(__DIR__, 4);
        $manifest = parseDevManifest($root . '/tools/dev-only-paths.txt');
        self::assertNotSame([], $manifest['rm']);
        self::assertNotSame([], $manifest['strip']);
        foreach ($manifest['strip'] as $rel) {
            self::assertFileExists($root . '/' . $rel, "strip 登记的文件不存在：{$rel}");
        }
    }
}
