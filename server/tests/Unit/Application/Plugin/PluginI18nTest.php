<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\Plugin;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\Plugin\PluginI18n;
use Sandadmin\Application\Plugin\PluginManifestLoader;

/** 插件语言包：domain=plugin-<code> 隔离，locale → zh_CN → key 兜底。 */
final class PluginI18nTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/plugin';

    private PluginI18n $i18n;

    protected function setUp(): void
    {
        $loader = new PluginManifestLoader(self::FIXTURES, static function (string $message): void {
        });
        $this->i18n = new PluginI18n($loader, self::FIXTURES);
    }

    public function testTransReturnsText(): void
    {
        self::assertSame('你好示例', $this->i18n->trans('sand-hello', 'menu.home'));
        self::assertSame('示例页', $this->i18n->trans('sand-hello', 'page.title'));
    }

    public function testUnknownKeyReturnsKey(): void
    {
        self::assertSame('menu.missing', $this->i18n->trans('sand-hello', 'menu.missing'));
    }

    public function testMissingLocaleFallsBack(): void
    {
        self::assertSame('你好示例', $this->i18n->trans('sand-hello', 'menu.home', [], 'en'));
    }

    public function testUndeclaredPluginReturnsKey(): void
    {
        self::assertSame('menu.home', $this->i18n->trans('sand-ghost', 'menu.home'));
    }

    public function testPlaceholderReplaced(): void
    {
        self::assertSame('你好，世界', $this->i18n->trans('sand-hello', 'page.welcome', ['name' => '世界']));
    }

    public function testNamespaceIsolatesSameKey(): void
    {
        self::assertSame('plugin-sand-hello', PluginI18n::domainOf('sand-hello'));
        self::assertSame('你好示例', $this->i18n->trans('sand-hello', 'menu.home'));
        self::assertSame('另一个插件', $this->i18n->trans('sand-other', 'menu.home'));
    }
}
