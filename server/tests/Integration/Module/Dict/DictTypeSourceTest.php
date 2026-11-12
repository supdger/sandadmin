<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Dict;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Dict\DictService;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\OrgSql;

/**
 * 选项组列表的来源字段：系统自带 / 插件带来 / 自建三种，
 * 插件组要带插件中文名，查不到插件行时兜底显示编码。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL。
 */
final class DictTypeSourceTest extends TestCase
{
    private static Connection $conn;
    private DictService $dicts;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        self::$conn = DriverManager::getConnection((new DsnParser())->parse($url));
        self::wipe();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$conn)) {
            self::wipe();
            self::$conn->close();
        }
    }

    protected function setUp(): void
    {
        self::wipe();
        $this->dicts = new DictService(self::$conn, new OrgAudit(self::$conn), new OrgIdempotency(null));
    }

    public function testListTypesReturnsSource(): void
    {
        $this->addPlugin('flowdemo', '流程演示');
        $this->addType('ck_src_sys', '甲源系统组', true, null);
        $this->addType('ck_src_ext', '甲源插件组', true, 'flowdemo.pet_colors');
        $this->addType('ck_src_ghost', '甲源残组', true, 'ghost.colors');
        $this->addType('ck_src_own', '甲源自建组', false, null);

        $found = [];
        foreach ($this->dicts->listTypes(null)['items'] as $item) {
            $code = OrgSql::str($item['code'] ?? null);
            if (str_starts_with($code, 'ck_src_')) {
                $found[$code] = $item;
            }
        }

        $this->assertSame(['kind' => 'system', 'plugin_label' => null], $found['ck_src_sys']['source'] ?? null);
        $this->assertSame(['kind' => 'plugin', 'plugin_label' => '流程演示'], $found['ck_src_ext']['source'] ?? null);
        $this->assertSame(['kind' => 'plugin', 'plugin_label' => 'ghost'], $found['ck_src_ghost']['source'] ?? null);
        $this->assertSame(['kind' => 'custom', 'plugin_label' => null], $found['ck_src_own']['source'] ?? null);
    }

    private function addType(string $code, string $name, bool $isSystem, ?string $extensionKey): void
    {
        self::$conn->executeStatement(
            'INSERT INTO sand_system_dict_type (code, name, is_system, extension_key)'
            . ' VALUES (:code, :name, :system, :ext)',
            [
                'code' => $code,
                'name' => $name,
                'system' => $isSystem ? 'true' : 'false',
                'ext' => $extensionKey,
            ],
        );
    }

    private function addPlugin(string $code, string $name): void
    {
        self::$conn->executeStatement(
            'INSERT INTO sand_system_plugin (code, name, version, host_constraint, manifest_sha256)'
            . ' VALUES (:code, :name, :version, :constraint, :sha)',
            [
                'code' => $code,
                'name' => $name,
                'version' => '1.0.0',
                'constraint' => '^1.0',
                'sha' => str_repeat('a', 64),
            ],
        );
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_dict_type WHERE code LIKE 'ck_src_%'");
        self::$conn->executeStatement("DELETE FROM sand_system_plugin WHERE code IN ('flowdemo', 'ghost')");
    }
}
