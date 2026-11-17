<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Auth;

use app\bootstrap\WeakPasswordGuard;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Auth\WeakPasswordException;
use Sandadmin\Infra\Auth\WeakPasswordList;
use Sandadmin\Infra\Auth\WeakPasswordRuntime;

final class WeakPasswordListTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        WeakPasswordRuntime::reset();
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->paths = [];
    }

    public function testHitReturnsTrue(): void
    {
        $list = $this->listWith(['password123456', 'woaini1314520']);
        self::assertTrue($list->isWeak('password123456'));
        self::assertTrue($list->isWeak('woaini1314520'));
    }

    public function testVendoredFileContainsKnownEntries(): void
    {
        $path = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . 'weak-passwords.txt';
        $list = WeakPasswordList::fromFile($path);
        self::assertTrue($list->isWeak('password123456'));
        self::assertTrue($list->isWeak('woaini1314520'));
        self::assertTrue($list->isWeak('1q2w3e4r5t6y'));
    }

    public function testMissReturnsFalse(): void
    {
        $list = $this->listWith(['password123456']);
        self::assertFalse($list->isWeak('UncommonPassw0rd!42'));
        self::assertFalse($list->isWeak(''));
    }

    public function testComparisonIgnoresCase(): void
    {
        $list = $this->listWith(['password123456']);
        self::assertTrue($list->isWeak('Password123456'));
        self::assertTrue($list->isWeak('PASSWORD123456'));
    }

    public function testComparisonIgnoresEdgeWhitespace(): void
    {
        $list = $this->listWith(['password123456']);
        self::assertTrue($list->isWeak('  password123456  '));
        self::assertTrue($list->isWeak("\tpassword123456\n"));
    }

    public function testRuntimeQueryAfterMount(): void
    {
        WeakPasswordRuntime::mount($this->listWith(['password123456']));
        self::assertTrue(WeakPasswordRuntime::ready());
        self::assertTrue(WeakPasswordRuntime::isWeak('Password123456'));
        WeakPasswordRuntime::reset();
        self::assertFalse(WeakPasswordRuntime::ready());
        self::assertFalse(WeakPasswordRuntime::isWeak('password123456'));
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(WeakPasswordException::class);
        WeakPasswordList::fromFile(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sand-no-such-wordlist.txt');
    }

    public function testEmptyFileThrows(): void
    {
        $path = $this->wordlistFile("\n  \n");
        $this->expectException(WeakPasswordException::class);
        WeakPasswordList::fromFile($path);
    }

    public function testMissingFileDegradesWhenUnmounted(): void
    {
        // 词表未挂载（开发环境告警降级路径）时检查放行。
        self::assertFalse(WeakPasswordRuntime::ready());
        self::assertFalse(WeakPasswordRuntime::isWeak('password123456'));
    }

    public function testGuardRejectsInProduction(): void
    {
        self::assertTrue(WeakPasswordGuard::mustReject('production', true));
        self::assertTrue(WeakPasswordGuard::mustReject('PROD', true));
    }

    public function testGuardAllowsInDev(): void
    {
        self::assertFalse(WeakPasswordGuard::mustReject('development', true));
        self::assertFalse(WeakPasswordGuard::mustReject('local', true));
        self::assertFalse(WeakPasswordGuard::mustReject(null, true));
    }

    public function testGuardAllowsWhenForbidWeakOff(): void
    {
        self::assertFalse(WeakPasswordGuard::mustReject('production', false));
    }

    /** @param list<string> $words */
    private function listWith(array $words): WeakPasswordList
    {
        return WeakPasswordList::fromFile($this->wordlistFile(implode("\n", $words) . "\n"));
    }

    private function wordlistFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sand-weakpw-');
        if ($path === false) {
            self::fail('创建临时文件失败');
        }
        file_put_contents($path, $content);
        $this->paths[] = $path;

        return $path;
    }
}
