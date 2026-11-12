<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Dict;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Dict\DictException;
use Sandadmin\Module\Dict\DictService;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\OrgSql;

/**
 * 选项组删除门槛：系统自带组和插件组（extension_key 非空）都不能删。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL。
 */
final class DictTypeRemovalTest extends TestCase
{
    private static Connection $conn;
    private DictService $dicts;
    private OrgActor $actor;

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
        $this->actor = new OrgActor(0, '测试账号', null, true, ['*']);
    }

    public function testSystemTypeRefusesDelete(): void
    {
        $typeId = $this->addType('ck_dict_sys', '甲测系统组', true, null);

        try {
            $this->dicts->removeType($typeId, $this->actor, 'req-dt1', '127.0.0.1', null);
            self::fail('系统自带的组必须拒绝删除');
        } catch (DictException $error) {
            self::assertSame('系统或插件在用，不能删除', $error->getMessage());
        }
        self::assertNotNull($this->fetchType($typeId));
    }

    public function testExtensionTypeRefusesDelete(): void
    {
        $typeId = $this->addType('ck_dict_ext', '甲测插件组', false, 'flow.source');

        try {
            $this->dicts->removeType($typeId, $this->actor, 'req-dt2', '127.0.0.1', null);
            self::fail('插件在用的组必须拒绝删除');
        } catch (DictException $error) {
            self::assertSame('系统或插件在用，不能删除', $error->getMessage());
        }
        self::assertNotNull($this->fetchType($typeId));
    }

    public function testPlainTypeDeletes(): void
    {
        $typeId = $this->addType('ck_dict_plain', '甲测普通组', false, null);

        $this->dicts->removeType($typeId, $this->actor, 'req-dt3', '127.0.0.1', null);

        self::assertNull($this->fetchType($typeId));
    }

    private function addType(string $code, string $name, bool $isSystem, ?string $extensionKey): int
    {
        return OrgSql::int(self::$conn->fetchOne(
            'INSERT INTO sand_system_dict_type (code, name, is_system, extension_key)'
            . ' VALUES (:code, :name, :system, :ext) RETURNING id',
            [
                'code' => $code,
                'name' => $name,
                'system' => $isSystem ? 'true' : 'false',
                'ext' => $extensionKey,
            ],
        ));
    }

    /** @return array<string, mixed>|null */
    private function fetchType(int $id): ?array
    {
        $row = self::$conn->fetchAssociative('SELECT id FROM sand_system_dict_type WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_dict_type WHERE code LIKE 'ck_dict_%'");
        self::$conn->executeStatement("DELETE FROM sand_system_audit_log WHERE actor_name = '测试账号'");
    }
}
