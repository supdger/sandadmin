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
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\OrgSql;

/**
 * 选项服务落库行为：来源标记、used 引用、审计与排序。
 * 需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL；
 * 种行一律带 cksvc 前缀，类首尾与每个用例前都清理。
 */
final class DictServiceTest extends TestCase
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
        // 0005 迁移的引用表：基线较旧的库可能还没有，缺才建
        self::$conn->executeStatement(
            'CREATE TABLE IF NOT EXISTS sand_system_dict_item_ref ('
            . ' item_id bigint NOT NULL,'
            . ' subject_type varchar(64) NOT NULL,'
            . ' subject_id varchar(64) NOT NULL,'
            . ' create_time timestamptz(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' CONSTRAINT pk_sand_system_dict_item_ref PRIMARY KEY (item_id, subject_type, subject_id),'
            . ' CONSTRAINT fk_sand_system_dict_item_ref_item FOREIGN KEY (item_id)'
            . ' REFERENCES sand_system_dict_item (id) ON DELETE CASCADE)'
        );
        self::$conn->executeStatement(
            'CREATE INDEX IF NOT EXISTS ix_sand_system_dict_item_ref_subject'
            . ' ON sand_system_dict_item_ref (subject_type, subject_id)'
        );
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
        $this->actor = new OrgActor(7, '测试管理员', null, true, ['*']);
    }

    private static function wipe(): void
    {
        self::$conn->executeStatement("DELETE FROM sand_system_dict_item_ref WHERE subject_type = 'cksvc'");
        self::$conn->executeStatement(
            "DELETE FROM sand_system_dict_item WHERE type_id IN (SELECT id FROM sand_system_dict_type WHERE name LIKE 'cksvc%')",
        );
        self::$conn->executeStatement("DELETE FROM sand_system_dict_type WHERE name LIKE 'cksvc%' OR code LIKE 'cksvc%'");
        self::$conn->executeStatement("DELETE FROM sand_system_plugin WHERE code LIKE 'cksvc%'");
        self::$conn->executeStatement("DELETE FROM sand_system_audit_log WHERE request_id LIKE 'cksvc%'");
    }

    /** @return array<string, mixed> */
    private function addType(string $name, bool $isSystem = false, ?string $extensionKey = null): array
    {
        $id = self::$conn->fetchOne(
            'INSERT INTO sand_system_dict_type (code, name, remark, is_system, extension_key)'
            . ' VALUES (:code, :name, NULL, :system, :ext) RETURNING id',
            [
                'code' => 'cksvc_' . $name,
                'name' => 'cksvc' . $name,
                'system' => $isSystem ? 'true' : 'false',
                'ext' => $extensionKey,
            ],
        );
        $row = self::$conn->fetchAssociative('SELECT * FROM sand_system_dict_type WHERE id = ?', [$id]);
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function addItem(int $typeId, string $label, bool $isDefault = false, bool $isSystem = false): array
    {
        $id = self::$conn->fetchOne(
            'INSERT INTO sand_system_dict_item (type_id, label, value, is_default, is_system)'
            . ' VALUES (:type, :label, :value, :default, :system) RETURNING id',
            [
                'type' => $typeId,
                'label' => $label,
                'value' => 'cksvc_item_' . $label,
                'default' => $isDefault ? 'true' : 'false',
                'system' => $isSystem ? 'true' : 'false',
            ],
        );
        $row = self::$conn->fetchAssociative('SELECT * FROM sand_system_dict_item WHERE id = ?', [$id]);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * 只看本测试种的组（库里可能有基线自带组）。
     *
     * @return list<array<string, mixed>>
     */
    private function ownTypeItems(): array
    {
        $own = [];
        foreach (self::rows($this->dicts->listTypes(null)['items']) as $item) {
            if (str_starts_with(OrgSql::str($item['code'] ?? null), 'cksvc_')) {
                $own[] = $item;
            }
        }

        return $own;
    }

    /** @return list<array<string, mixed>> */
    private function showItems(int $typeId): array
    {
        return self::rows($this->dicts->showType($typeId)['items'] ?? null);
    }

    /** @return list<array<string, mixed>> */
    private static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $rows = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($item as $name => $field) {
                if (is_string($name)) {
                    $row[$name] = $field;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function testListTypesReturnsItemsWithSource(): void
    {
        self::$conn->insert('sand_system_plugin', [
            'code' => 'cksvc_flow',
            'name' => '流程演示',
            'version' => '1.0.0',
            'host_constraint' => '^1.0',
            'manifest_sha256' => str_repeat('a', 64),
        ]);
        $this->addType('甲组系统', true);
        $this->addType('甲组插件', true, 'cksvc_flow.pet_colors');
        $this->addType('甲组残组', true, 'ghost.colors');
        $this->addType('甲组自建', false);
        $types = $this->ownTypeItems();

        self::assertCount(4, $types);
        self::assertSame(['kind' => 'system', 'plugin_label' => null], $types[0]['source']);
        self::assertSame(['kind' => 'plugin', 'plugin_label' => '流程演示'], $types[1]['source']);
        self::assertSame(['kind' => 'plugin', 'plugin_label' => 'ghost'], $types[2]['source']);
        self::assertSame(['kind' => 'custom', 'plugin_label' => null], $types[3]['source']);
    }

    public function testListTypesKeywordFilter(): void
    {
        $this->addType('关键词甲');
        $this->addType('别的组');

        $own = [];
        foreach (self::rows($this->dicts->listTypes('关键词')['items']) as $item) {
            if (str_starts_with(OrgSql::str($item['code'] ?? null), 'cksvc_')) {
                $own[] = $item;
            }
        }

        self::assertCount(1, $own);
        self::assertSame('cksvc关键词甲', $own[0]['name']);
    }

    public function testListTypesCountsItems(): void
    {
        $type = $this->addType('乙组');
        $typeId = OrgSql::int($type['id'] ?? null);
        $this->addItem($typeId, '一');
        $this->addItem($typeId, '二');
        $items = $this->ownTypeItems();

        self::assertSame(2, OrgSql::int($items[0]['item_count'] ?? null));
        self::assertSame('cksvc乙组', $items[0]['name']);
        self::assertStringStartsWith('cksvc_', OrgSql::str($items[0]['code'] ?? null));
    }

    public function testShowTypeMarksUsedFlag(): void
    {
        $type = $this->addType('丙组');
        $typeId = OrgSql::int($type['id'] ?? null);
        $used = $this->addItem($typeId, '已引用');
        $this->addItem($typeId, '未引用');
        self::$conn->insert('sand_system_dict_item_ref', [
            'item_id' => OrgSql::int($used['id'] ?? null),
            'subject_type' => 'cksvc',
            'subject_id' => '1',
        ]);
        $pluginType = $this->addType('丙组插件', true, 'ghost.k');
        $this->addItem(OrgSql::int($pluginType['id'] ?? null), '插件项');

        $items = $this->showItems($typeId);
        self::assertTrue($items[0]['used']);
        self::assertFalse($items[1]['used']);
        // 插件组无引用行时 used 为 null，前端按可能已使用提醒
        $pluginItems = $this->showItems(OrgSql::int($pluginType['id'] ?? null));
        self::assertNull($pluginItems[0]['used']);
        self::assertSame((string) $typeId, $items[0]['type_id']);
        self::assertSame((string) $typeId, $items[1]['type_id']);
    }

    public function testShowTypeMissingThrows(): void
    {
        $this->expectException(DictException::class);
        $this->dicts->showType(999999999);
    }

    public function testCreateTypeWritesRowAndAudit(): void
    {
        $created = $this->dicts->createType(
            ['name' => 'cksvc新分组', 'remark' => '备注一'],
            $this->actor,
            'cksvc_req1',
            null,
            null,
        );

        self::assertSame('cksvc新分组', $created['name']);
        self::assertStringStartsWith('dict_', OrgSql::str($created['code'] ?? null));
        self::assertSame('备注一', $created['remark']);
        self::assertFalse($created['is_system']);
        self::assertSame(['kind' => 'custom', 'plugin_label' => null], $created['source']);
        $audit = self::$conn->fetchAssociative("SELECT * FROM sand_system_audit_log WHERE request_id = 'cksvc_req1'");
        self::assertIsArray($audit);
        self::assertSame('dict', $audit['module']);
        self::assertSame('create', $audit['action']);
        self::assertSame('dict_type', $audit['target_type']);
        self::assertSame('7', OrgSql::str($audit['actor_id'] ?? null));
    }

    public function testUpdateTypeChangesNameAndRemark(): void
    {
        $type = $this->addType('旧名');
        $saved = $this->dicts->updateType(
            OrgSql::int($type['id'] ?? null),
            ['name' => 'cksvc新名', 'remark' => '改过'],
            $this->actor,
            'cksvc_req2',
            null,
            null,
        );

        self::assertSame('cksvc新名', $saved['name']);
        self::assertSame('改过', $saved['remark']);
        $audit = self::$conn->fetchAssociative(
            "SELECT changes FROM sand_system_audit_log WHERE request_id = 'cksvc_req2' AND action = 'update'",
        );
        self::assertIsArray($audit);
        self::assertSame(
            ['name' => ['before' => 'cksvc旧名', 'after' => 'cksvc新名']],
            json_decode(OrgSql::str($audit['changes'] ?? null), true),
        );
    }

    public function testRemoveTypeDeletesCustom(): void
    {
        $type = $this->addType('能删的组');
        $id = OrgSql::int($type['id'] ?? null);
        $this->dicts->removeType($id, $this->actor, 'cksvc_req3', null, null);

        self::assertFalse(
            self::$conn->fetchOne('SELECT 1 FROM sand_system_dict_type WHERE id = ?', [$id]),
        );
    }

    public function testRemoveTypeRejectsSystemAndExtension(): void
    {
        $system = $this->addType('系统组', true);
        $extension = $this->addType('插件组', false, 'cksvc_flow.colors');

        foreach ([$system, $extension] as $row) {
            try {
                $this->dicts->removeType(OrgSql::int($row['id'] ?? null), $this->actor, 'cksvc_req4', null, null);
                self::fail('系统或插件组应拒删');
            } catch (DictException $e) {
                self::assertSame(422, $e->status);
                self::assertSame('系统或插件在用，不能删除', $e->getMessage());
            }
        }
    }

    public function testCreateItemWritesRow(): void
    {
        $type = $this->addType('丁组');
        $item = $this->dicts->createItem(
            OrgSql::int($type['id'] ?? null),
            ['label' => 'cksvc甲选项', 'color' => '#3491FA', 'sort' => 3],
            $this->actor,
            'cksvc_req5',
            null,
            null,
        );

        self::assertSame('cksvc甲选项', $item['label']);
        self::assertSame('#3491FA', $item['color']);
        self::assertStringStartsWith('item_', OrgSql::str($item['value'] ?? null));
        self::assertSame(3, $item['sort']);
        self::assertSame('enabled', $item['status']);
        self::assertFalse($item['is_default']);
        self::assertFalse($item['used']);
    }

    public function testCreateItemValidatesInput(): void
    {
        $type = $this->addType('校验组');
        $typeId = OrgSql::int($type['id'] ?? null);

        foreach ([
            [['label' => str_repeat('很', 21)], 'label'],
            [['label' => '甲', 'color' => '#000000'], 'color'],
            [['label' => '甲', 'is_default' => 'yes'], 'is_default'],
        ] as [$body, $field]) {
            try {
                $this->dicts->createItem($typeId, $body, $this->actor, 'cksvc_req6', null, null);
                self::fail("应抛 {$field} 校验异常");
            } catch (DictException $e) {
                self::assertSame(422, $e->status);
                self::assertArrayHasKey($field, $e->fields);
            }
        }
        try {
            $this->dicts->createItem($typeId, ['label' => '甲', 'sort' => 'abc'], $this->actor, 'cksvc_req6', null, null);
            self::fail('非法 sort 应抛异常');
        } catch (OrgException $e) {
            self::assertArrayHasKey('sort', $e->fields);
        }
    }

    public function testCreateItemClearsPreviousDefault(): void
    {
        $type = $this->addType('默认组');
        $typeId = OrgSql::int($type['id'] ?? null);
        $old = $this->addItem($typeId, '旧默认', true);

        $this->dicts->createItem($typeId, ['label' => 'cksvc新默认', 'is_default' => true], $this->actor, 'cksvc_req7', null, null);

        $items = $this->showItems($typeId);
        self::assertFalse($items[0]['is_default'], '旧默认项应被清掉');
        self::assertSame(OrgSql::str($old['id'] ?? null), $items[0]['id']);
    }

    public function testUpdateItemKeepsStatusWhenAbsent(): void
    {
        $type = $this->addType('戊组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '原选项');
        $itemId = OrgSql::int($item['id'] ?? null);
        $this->dicts->setItemStatus($itemId, 2, $this->actor, 'cksvc_req8', null, null);

        $saved = $this->dicts->updateItem(
            $itemId,
            ['label' => 'cksvc改名', 'color' => '#13C2C2'],
            $this->actor,
            'cksvc_req8',
            null,
            null,
        );

        self::assertSame('cksvc改名', $saved['label']);
        self::assertSame('#13C2C2', $saved['color']);
        self::assertSame('disabled', $saved['status'], '请求没带 status 时应沿用库里的停用态');
    }

    public function testSetItemStatusToggles(): void
    {
        $type = $this->addType('己组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '甲');
        $itemId = OrgSql::int($item['id'] ?? null);

        $this->dicts->setItemStatus($itemId, 2, $this->actor, 'cksvc_req9', null, null);
        self::assertSame('disabled', $this->statusOf($itemId));
        $this->dicts->setItemStatus($itemId, 1, $this->actor, 'cksvc_req9', null, null);
        self::assertSame('enabled', $this->statusOf($itemId));
    }

    public function testRemoveItemRejectsSystemItem(): void
    {
        $type = $this->addType('庚组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '系统项', false, true);

        $this->expectException(DictException::class);
        $this->dicts->removeItem(OrgSql::int($item['id'] ?? null), $this->actor, 'cksvc_req10', null, null);
    }

    public function testRemoveItemDeletesCustomItem(): void
    {
        $type = $this->addType('辛组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '可删');
        $itemId = OrgSql::int($item['id'] ?? null);
        $this->dicts->removeItem($itemId, $this->actor, 'cksvc_req11', null, null);

        self::assertFalse(
            self::$conn->fetchOne('SELECT 1 FROM sand_system_dict_item WHERE id = ?', [$itemId]),
        );
    }

    public function testSortItemsReorders(): void
    {
        $type = $this->addType('排序组');
        $typeId = OrgSql::int($type['id'] ?? null);
        $a = $this->addItem($typeId, '甲');
        $b = $this->addItem($typeId, '乙');
        $third = $this->addItem($typeId, '丙');

        $this->dicts->sortItems(
            $typeId,
            [OrgSql::int($third['id'] ?? null), OrgSql::int($a['id'] ?? null), OrgSql::int($b['id'] ?? null)],
            $this->actor,
            'cksvc_req12',
            null,
            null,
        );

        $labels = array_column($this->showItems($typeId), 'label');
        self::assertSame(['丙', '甲', '乙'], $labels);
    }

    public function testMissingItemThrows(): void
    {
        $this->expectException(DictException::class);
        $this->dicts->updateItem(999999999, ['label' => 'x'], $this->actor, 'cksvc_req13', null, null);
    }

    private function statusOf(int $itemId): string
    {
        foreach ($this->ownTypeItems() as $type) {
            foreach ($this->showItems(OrgSql::int($type['id'] ?? null)) as $item) {
                if (OrgSql::int($item['id'] ?? null) === $itemId) {
                    return OrgSql::str($item['status'] ?? null);
                }
            }
        }
        self::fail('选项不存在');
    }
}
