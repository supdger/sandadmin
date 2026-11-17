<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Plugin\PluginManifestLoader;

/** 插件清单装载：缺失、坏 JSON、字段不合法都按无声明处理并记 warning。 */
final class PluginManifestLoaderTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/plugin';
    private const TMP = __DIR__ . '/../../../tmp/manifest-loader';

    /** @var list<string> */
    private array $warnings = [];

    private PluginManifestLoader $loader;

    protected function setUp(): void
    {
        $this->warnings = [];
        $this->loader = new PluginManifestLoader(self::TMP, function (string $message): void {
            $this->warnings[] = $message;
        });
        self::clearDir(self::TMP);
        mkdir(self::TMP, 0777, true);
    }

    protected function tearDown(): void
    {
        self::clearDir(self::TMP);
    }

    private function fixtureLoader(): PluginManifestLoader
    {
        return new PluginManifestLoader(self::FIXTURES, function (string $message): void {
            $this->warnings[] = $message;
        });
    }

    /** @param array<string, mixed> $manifest */
    private function plant(string $code, array $manifest): void
    {
        $dir = self::TMP . '/' . $code;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE));
    }

    private static function clearDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }

    public function testParsesMenusAndLocales(): void
    {
        $manifest = $this->fixtureLoader()->load('sand-hello');
        self::assertTrue($manifest->declaresMenus());
        self::assertCount(2, $manifest->menus);

        $home = $manifest->menus[0];
        self::assertSame('home', $home->key);
        self::assertSame('/plugin/sand-hello', $home->path);
        self::assertSame('你好示例', $home->title);
        self::assertSame('menu.home', $home->titleKey);
        self::assertSame('apps', $home->icon);
        self::assertSame('sand_hello:page:read', $home->permission);
        self::assertSame([], $home->children);

        $more = $manifest->menus[1];
        self::assertSame('more', $more->key);
        self::assertSame(9, $more->sort);
        self::assertCount(1, $more->children);
        self::assertSame('/plugin/sand-hello/more/about', $more->children[0]->path);

        $locales = $manifest->locales;
        if ($locales === null) {
            self::fail('locales 段应被解析');
        }
        self::assertSame('resource/translations', $locales->backend);
        self::assertSame('plugin-web/sand-hello/locales', $locales->frontend);
        self::assertSame([], $this->warnings);
    }

    public function testMissingManifestReturnsEmpty(): void
    {
        $manifest = $this->fixtureLoader()->load('sand-ghost');
        self::assertFalse($manifest->declaresMenus());
        self::assertNull($manifest->locales);
        self::assertCount(1, $this->warnings);
    }

    public function testBadJsonReturnsEmpty(): void
    {
        mkdir(self::TMP . '/sand-broken', 0777, true);
        file_put_contents(self::TMP . '/sand-broken/manifest.json', '{bad json');
        $manifest = $this->loader->load('sand-broken');
        self::assertFalse($manifest->declaresMenus());
        self::assertCount(1, $this->warnings);
    }

    public function testUnknownFieldRejected(): void
    {
        mkdir(self::TMP . '/sand-bad', 0777, true);
        copy(self::FIXTURES . '/sand-hello/manifest-bad.json', self::TMP . '/sand-bad/manifest.json');
        $manifest = $this->loader->load('sand-bad');
        self::assertFalse($manifest->declaresMenus());
        self::assertCount(1, $this->warnings);
    }

    public function testMenuPathPrefixEnforced(): void
    {
        $this->plant('sand-evil', [
            'menus' => [['key' => 'home', 'path' => '/plugin/sand-hello', 'title' => '借道']],
        ]);
        self::assertFalse($this->loader->load('sand-evil')->declaresMenus());

        $this->plant('sand-good', [
            'menus' => [['key' => 'home', 'path' => '/plugin/sand-good', 'title' => '自家']],
        ]);
        self::assertTrue($this->loader->load('sand-good')->declaresMenus());
    }

    public function testMenuKeyCharsEnforced(): void
    {
        $this->plant('sand-case', [
            'menus' => [['key' => 'Home', 'path' => '/plugin/sand-case', 'title' => '大写']],
        ]);
        self::assertFalse($this->loader->load('sand-case')->declaresMenus());
    }

    public function testThirdLevelChildrenRejected(): void
    {
        $this->plant('sand-deep', [
            'menus' => [[
                'key' => 'a',
                'path' => '/plugin/sand-deep/a',
                'title' => '一层',
                'children' => [[
                    'key' => 'b',
                    'path' => '/plugin/sand-deep/a/b',
                    'title' => '二层',
                    'children' => [['key' => 'c', 'path' => '/plugin/sand-deep/a/b/c', 'title' => '三层']],
                ]],
            ]],
        ]);
        self::assertFalse($this->loader->load('sand-deep')->declaresMenus());
    }

    public function testLocalesDirTraversalRejected(): void
    {
        $this->plant('sand-walk', [
            'locales' => ['backend' => '../escape'],
        ]);
        $manifest = $this->loader->load('sand-walk');
        self::assertNull($manifest->locales);
        self::assertCount(1, $this->warnings);
    }

    public function testLoadCachesResult(): void
    {
        $loader = $this->fixtureLoader();
        self::assertSame($loader->load('sand-hello'), $loader->load('sand-hello'));
    }
}
