<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Audit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Audit\AuditModuleOptions;

/**
 * 只在 PostgreSQL 上跑（sand_system_plugin 基线表），设置 SAND_TEST_DB_URL 才执行；
 * 要求库内已应用 0001 基线。每个用例清空插件表保证断言确定。
 */
final class AuditModuleOptionsTest extends TestCase
{
    private const HOST_COUNT = 20;

    private Connection $conn;
    private AuditModuleOptions $options;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        $this->conn = DriverManager::getConnection((new DsnParser())->parse($url));
        $this->conn->executeStatement('DELETE FROM sand_system_plugin');
        $this->options = new AuditModuleOptions($this->conn);
    }

    protected function tearDown(): void
    {
        if (!isset($this->conn)) {
            return;
        }
        $this->conn->close();
    }

    private function addPlugin(string $code, string $name, string $state = 'installed'): void
    {
        $this->conn->executeStatement(
            'INSERT INTO sand_system_plugin (code, name, version, host_constraint, manifest_sha256, state)'
            . ' VALUES (?, ?, ?, ?, ?, ?)',
            [$code, $name, '1.0.0', '^1.0', str_repeat('a', 64), $state],
        );
    }

    public function testHostModulesComeFirstInFixedOrder(): void
    {
        $options = $this->options->options();
        self::assertCount(self::HOST_COUNT, $options);
        self::assertSame(['key' => 'user', 'name' => '用户管理'], $options[0]->jsonSerialize());
        self::assertSame('sand_ai', $options[self::HOST_COUNT - 1]->key);
    }

    public function testInstalledPluginsAppendedSortedByCode(): void
    {
        $this->addPlugin('bbb', '插件B');
        $this->addPlugin('aaa', '插件A');
        // 未安装的插件不出现在选项里
        $this->addPlugin('zzz', '已卸载插件', 'uninstalled');

        $options = $this->options->options();
        self::assertCount(self::HOST_COUNT + 2, $options);
        $tail = [];
        foreach (array_slice($options, self::HOST_COUNT) as $option) {
            $tail[] = $option->jsonSerialize();
        }
        self::assertSame(
            [['key' => 'aaa', 'name' => '插件A'], ['key' => 'bbb', 'name' => '插件B']],
            $tail,
        );
    }

    public function testPluginCodeCollidingWithHostKeyIsDropped(): void
    {
        $this->addPlugin('user', '同名插件');
        $options = $this->options->options();
        self::assertCount(self::HOST_COUNT, $options);
        self::assertSame('用户管理', $options[0]->name);
    }
}
