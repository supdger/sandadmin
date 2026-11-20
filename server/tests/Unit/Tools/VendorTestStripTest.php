<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;

$root = dirname(__DIR__, 4);
require_once $root . '/tools/strip-vendor-tests.php';

/** 发布包第三方测试文件剔除：文件匹配、目录剔除与前端载荷判定。 */
final class VendorTestStripTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/vstrip-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/server/vendor/acme/pkg', 0777, true);
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

    private function write(string $rel, string $content = 'x'): void
    {
        $path = $this->dir . '/' . $rel;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    public function testIsVendorTestFile(): void
    {
        self::assertTrue(isVendorTestFile('a.test.ts'));
        self::assertTrue(isVendorTestFile('a.spec.tsx'));
        self::assertTrue(isVendorTestFile('a.test.d.ts'));
        self::assertFalse(isVendorTestFile('a.testts'));
        self::assertFalse(isVendorTestFile('LatestTest.php'));
        self::assertFalse(isVendorTestFile('a.ts'));
    }

    public function testCollectsTestFilesAndTestsDirs(): void
    {
        $this->write('server/vendor/acme/pkg/src/x.test.ts');
        $this->write('server/vendor/acme/pkg/src/a.spec.tsx');
        $this->write('server/vendor/acme/pkg/src/index.ts');
        $this->write('server/vendor/acme/pkg/__tests__/b.ts');
        // 前端载荷内的 tests 目录（包内有 .ts 源码）
        $this->write('server/vendor/acme/pkg/tests/c.ts');
        // PHP 包的 tests 目录：无前端源码，不算前端载荷，保留
        $this->write('server/vendor/acme/lib/tests/ParseTest.php');
        // 命中名但不是测试文件命名
        $this->write('server/vendor/acme/pkg/src/LatestTest.php');

        $found = collectVendorTestPaths($this->dir);
        $names = array_map('basename', $found['files']);
        sort($names);
        self::assertSame(['a.spec.tsx', 'x.test.ts'], $names);
        $dirNames = array_map('basename', $found['dirs']);
        sort($dirNames);
        self::assertSame(['__tests__', 'tests'], $dirNames);
        // tests 命中的是 acme/pkg 下的，不是 acme/lib 下的
        self::assertContains($this->dir . '/server/vendor/acme/pkg/tests', $found['dirs']);
        self::assertNotContains($this->dir . '/server/vendor/acme/lib/tests', $found['dirs']);
    }

    public function testFrontendPayloadDetectionByPackageJson(): void
    {
        $this->write('server/vendor/acme/pkg/package.json', '{}');
        // 无 .ts 源码时 package.json 也算前端载荷
        self::assertTrue(insideFrontendPayload(
            $this->dir . '/server/vendor/acme/pkg/tests',
            $this->dir . '/server/vendor/acme/pkg',
        ));
    }

    public function testPhpPackageTestsDirIsNotPayload(): void
    {
        $this->write('server/vendor/acme/lib/src/Parse.php', '<?php');
        $this->write('server/vendor/acme/lib/tests/ParseTest.php', '<?php');
        self::assertFalse(insideFrontendPayload(
            $this->dir . '/server/vendor/acme/lib/tests',
            $this->dir . '/server/vendor/acme/lib',
        ));
    }

    public function testPluginAreaUsesSingleLevelPackageRoot(): void
    {
        $this->write('server/plugin/demo/web/src/x.ts');
        $this->write('server/plugin/demo/tests/y.test.ts');
        $root = packageRootPath(
            $this->dir . '/server/plugin',
            'server/plugin',
            $this->dir . '/server/plugin/demo/tests',
        );
        self::assertSame($this->dir . '/server/plugin/demo', $root);
    }
}
