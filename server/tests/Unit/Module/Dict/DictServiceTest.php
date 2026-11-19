<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Dict;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Dict\DictException;
use Sandadmin\Module\Dict\DictService;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\OrgSql;

/**
 * 选项模块的内存库样例：sqlite 内存连接 + 最小建表，不依赖 SAND_TEST_DB_URL。
 * 布尔列手工写 1/0——服务按 PG 驱动返回值（true/'t'/1/'1'）归一，
 * sqlite 存 'true' 文本不归一，夹具行统一用整数。
 */
final class DictServiceTest extends TestCase
{
    private Connection $conn;
    private DictService $dicts;
    private OrgActor $actor;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->schema();
        $this->dicts = new DictService($this->conn, new OrgAudit($this->conn), new OrgIdempotency(null));
        $this->actor = new OrgActor(0, '测试账号', null, true, ['*']);
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    private function schema(): void
    {
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_dict_type (id integer primary key autoincrement,
                code varchar(64) not null, name varchar(64) not null, remark text null,
                is_system integer not null default 0, extension_key varchar(128) null,
                created_by integer null, updated_by integer null, update_time text null)',
        );
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_dict_item (id integer primary key autoincrement,
                type_id integer not null, label varchar(64) not null, value varchar(64) not null,
                color varchar(16) null, is_default integer not null default 0,
                is_system integer not null default 0, sort integer not null default 0,
                status integer not null default 1, created_by integer null, updated_by integer null,
                create_time text null, update_time text null)',
        );
        $this->conn->executeStatement('CREATE TABLE sand_system_dict_item_ref (item_id integer not null)');
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_plugin (code varchar(64) primary key, name varchar(128) not null)',
        );
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_audit_log (actor_type varchar(16) null, actor_id integer null,
                actor_name varchar(64) null, module varchar(32) null, action varchar(32) null,
                action_text varchar(255) null, target_type varchar(32) null, target_id varchar(64) null,
                target_label varchar(128) null, succeeded varchar(8) null, changes text null,
                ip varchar(64) null, location varchar(64) null, device_label varchar(64) null,
                request_id varchar(64) null)',
        );
    }

    private function addType(string $code, string $name, int $isSystem = 0, ?string $extensionKey = null): int
    {
        return OrgSql::int($this->conn->fetchOne(
            'INSERT INTO sand_system_dict_type (code, name, is_system, extension_key)'
            . ' VALUES (:code, :name, :system, :ext) RETURNING id',
            ['code' => $code, 'name' => $name, 'system' => $isSystem, 'ext' => $extensionKey],
        ));
    }

    private function addItem(int $typeId, string $label, int $isSystem = 0): int
    {
        return OrgSql::int($this->conn->fetchOne(
            'INSERT INTO sand_system_dict_item (type_id, label, value, is_system)'
            . ' VALUES (:type, :label, :value, :system) RETURNING id',
            ['type' => $typeId, 'label' => $label, 'value' => 'v_' . $label, 'system' => $isSystem],
        ));
    }

    /**
     * @param array<mixed> $items
     *
     * @return array<string, mixed>
     */
    private function findByCode(array $items, string $code): array
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            /** @var array<string, mixed> $item */
            if (($item['code'] ?? null) === $code) {
                return $item;
            }
        }
        self::fail('未找到选项组 ' . $code);
    }

    public function testListTypesShowsSourceKinds(): void
    {
        $this->conn->insert('sand_system_plugin', ['code' => 'flowdemo', 'name' => '流程演示']);
        $this->addType('ut_src_sys', '系统组', 1);
        $this->addType('ut_src_ext', '插件组', 0, 'flowdemo.colors');
        $this->addType('ut_src_ghost', '残留插件组', 0, 'ghost.colors');
        $this->addType('ut_src_own', '自建组');

        $items = $this->dicts->listTypes(null)['items'];

        self::assertSame(['kind' => 'system', 'plugin_label' => null], $this->findByCode($items, 'ut_src_sys')['source']);
        self::assertSame(['kind' => 'plugin', 'plugin_label' => '流程演示'], $this->findByCode($items, 'ut_src_ext')['source']);
        self::assertSame(['kind' => 'plugin', 'plugin_label' => 'ghost'], $this->findByCode($items, 'ut_src_ghost')['source']);
        self::assertSame(['kind' => 'custom', 'plugin_label' => null], $this->findByCode($items, 'ut_src_own')['source']);
    }

    public function testShowTypeMarksUsedFlag(): void
    {
        $plainId = $this->addType('ut_used_plain', '普通组');
        $itemWithRef = $this->addItem($plainId, '被引用');
        $itemFree = $this->addItem($plainId, '未引用');
        $this->conn->insert('sand_system_dict_item_ref', ['item_id' => $itemWithRef]);
        $extId = $this->addType('ut_used_ext', '插件组', 0, 'flowdemo.colors');
        $extItem = $this->addItem($extId, '插件项');

        /** @var list<array<string, mixed>> $plain */
        $plain = $this->dicts->showType($plainId)['items'];
        /** @var list<array<string, mixed>> $ext */
        $ext = $this->dicts->showType($extId)['items'];

        self::assertTrue($plain[0]['used']);
        self::assertFalse($plain[1]['used']);
        // 插件组查不到引用行时是 null：可能已被插件代码引用，调用方按已使用提醒
        self::assertNull($ext[0]['used']);
        self::assertSame($itemFree, OrgSql::int($plain[1]['id']));
    }

    public function testCreateTypeWritesRowAndAudit(): void
    {
        $type = $this->dicts->createType(['name' => '甲测组', 'remark' => '说明'], $this->actor, 'req-1', null, null);

        self::assertSame('甲测组', $type['name']);
        self::assertSame('说明', $type['remark']);
        $source = $type['source'];
        self::assertIsArray($source);
        self::assertSame('custom', $source['kind']);
        $audit = $this->conn->fetchAssociative(
            "SELECT module, action, target_type FROM sand_system_audit_log WHERE request_id = 'req-1'",
        );
        self::assertSame(['module' => 'dict', 'action' => 'create', 'target_type' => 'dict_type'], $audit);
    }

    public function testCreateTypeRejectsLongName(): void
    {
        try {
            $this->dicts->createType(['name' => str_repeat('长', 21)], $this->actor, 'req-2', null, null);
            self::fail('组名超过 20 字应拒绝');
        } catch (DictException $error) {
            self::assertSame(422, $error->status);
            self::assertArrayHasKey('name', $error->fields);
        }
    }

    public function testRemoveTypeRefusesSystemAndPlugin(): void
    {
        $systemId = $this->addType('ut_rm_sys', '系统组', 1);
        $extId = $this->addType('ut_rm_ext', '插件组', 0, 'flowdemo.colors');

        foreach ([$systemId, $extId] as $id) {
            try {
                $this->dicts->removeType($id, $this->actor, 'req-3', null, null);
                self::fail('系统或插件在用的组必须拒绝删除');
            } catch (DictException $error) {
                self::assertSame('系统或插件在用，不能删除', $error->getMessage());
            }
        }
    }

    public function testRemoveTypeDeletesCustom(): void
    {
        $id = $this->addType('ut_rm_own', '自建组');

        $this->dicts->removeType($id, $this->actor, 'req-4', null, null);

        self::assertFalse($this->conn->fetchAssociative('SELECT id FROM sand_system_dict_type WHERE id = ?', [$id]));
    }

    public function testCreateItemRejectsBadInput(): void
    {
        $typeId = $this->addType('ut_item_bad', '校验组');

        $cases = [
            [['label' => str_repeat('长', 21)], 'label'],
            [['label' => '甲', 'color' => '#000000'], 'color'],
            [['label' => '甲', 'is_default' => 'yes'], 'is_default'],
        ];
        foreach ($cases as [$body, $field]) {
            try {
                $this->dicts->createItem($typeId, $body, $this->actor, 'req-5', null, null);
                self::fail('字段 ' . $field . ' 应拒绝');
            } catch (DictException $error) {
                self::assertSame(422, $error->status);
                self::assertArrayHasKey($field, $error->fields);
            }
        }
    }

    public function testCreateItemClearsOtherDefault(): void
    {
        $typeId = $this->addType('ut_item_def', '默认组');
        $old = $this->addItem($typeId, '旧默认');
        $this->conn->executeStatement('UPDATE sand_system_dict_item SET is_default = 1 WHERE id = ?', [$old]);

        $created = $this->dicts->createItem(
            $typeId,
            ['label' => '新默认', 'is_default' => true],
            $this->actor,
            'req-6',
            null,
            null,
        );

        self::assertSame('新默认', $created['label']);
        self::assertSame(0, OrgSql::int($this->conn->fetchOne(
            'SELECT is_default FROM sand_system_dict_item WHERE id = ?',
            [$old],
        )));
    }

    public function testRemoveItemRefusesSystem(): void
    {
        $typeId = $this->addType('ut_rm_item', '选项组');
        $itemId = $this->addItem($typeId, '系统项', 1);

        try {
            $this->dicts->removeItem($itemId, $this->actor, 'req-7', null, null);
            self::fail('系统自带的选项必须拒绝删除');
        } catch (DictException $error) {
            self::assertSame('系统自带的选项不能删除', $error->getMessage());
        }
    }

    public function testSetItemStatusToggles(): void
    {
        $typeId = $this->addType('ut_status', '状态组');
        $itemId = $this->addItem($typeId, '选项');

        $this->dicts->setItemStatus($itemId, 2, $this->actor, 'req-8', null, null);

        self::assertSame(2, OrgSql::int($this->conn->fetchOne(
            'SELECT status FROM sand_system_dict_item WHERE id = ?',
            [$itemId],
        )));
    }

    public function testSortItemsRewritesOrder(): void
    {
        $typeId = $this->addType('ut_sort', '排序组');
        $itemA = $this->addItem($typeId, '甲');
        $itemB = $this->addItem($typeId, '乙');
        $itemC = $this->addItem($typeId, '丙');

        $this->dicts->sortItems($typeId, [$itemC, $itemA, $itemB], $this->actor, 'req-9', null, null);

        $sorts = $this->conn->fetchAllKeyValue(
            'SELECT id, sort FROM sand_system_dict_item WHERE type_id = ?',
            [$typeId],
        );
        self::assertSame(0, OrgSql::int($sorts[$itemC]));
        self::assertSame(1, OrgSql::int($sorts[$itemA]));
        self::assertSame(2, OrgSql::int($sorts[$itemB]));
    }

    public function testShowTypeNotFoundThrows(): void
    {
        try {
            $this->dicts->showType(999);
            self::fail('不存在的组应抛 404');
        } catch (DictException $error) {
            self::assertSame(404, $error->status);
        }
    }
}
