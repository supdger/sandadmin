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
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\OrgSql;
use Sandadmin\Tests\Support\Factory\UserFactory;

/**
 * 选项服务的内存库用例：DBAL pdo_sqlite 内存连接 + 最小建表，不连外部库。
 * 与 PG 的差异留在夹具：布尔夹具行写整数 1/0（服务按 true/'t'/1/'1' 归一，
 * sqlite 存 'true' 文本不归一）；关键词查询的 ILIKE 与重名唯一约束映射是
 * 方言行为，由 Integration 覆盖，这里不断言。
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
        $user = UserFactory::make(id: 7, nickname: '测试管理员', isSuper: true);
        $this->actor = new OrgActor($user->id, $user->nickname, $user->deptId, $user->isSuper, ['*']);
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
                created_by integer null, updated_by integer null,
                create_time text null, update_time text null)',
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
            'CREATE TABLE sand_system_audit_log (id integer primary key autoincrement,
                actor_type varchar(16) null, actor_id integer null, actor_name varchar(32) null,
                module varchar(32) null, action varchar(32) null, action_text varchar(255) null,
                target_type varchar(32) null, target_id varchar(64) null, target_label varchar(128) null,
                succeeded varchar(8) null, changes text null, ip varchar(64) null,
                location varchar(64) null, device_label varchar(64) null, request_id varchar(64) null)',
        );
        $this->conn->executeStatement(
            'CREATE TABLE sand_system_plugin (code varchar(64) primary key, name varchar(128) not null)',
        );
    }

    /** @return array<string, mixed> */
    private function addType(string $name, int $isSystem = 0, ?string $extensionKey = null): array
    {
        $this->conn->insert('sand_system_dict_type', [
            'code' => 'dict_' . $name,
            'name' => $name,
            'remark' => null,
            'is_system' => $isSystem,
            'extension_key' => $extensionKey,
        ]);
        $id = (int) $this->conn->lastInsertId();
        $row = $this->conn->fetchAssociative('SELECT * FROM sand_system_dict_type WHERE id = ?', [$id]);
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function addItem(int $typeId, string $label, int $isDefault = 0, int $isSystem = 0): array
    {
        $this->conn->insert('sand_system_dict_item', [
            'type_id' => $typeId,
            'label' => $label,
            'value' => 'item_' . $label,
            'is_default' => $isDefault,
            'is_system' => $isSystem,
        ]);
        $id = (int) $this->conn->lastInsertId();
        $row = $this->conn->fetchAssociative('SELECT * FROM sand_system_dict_item WHERE id = ?', [$id]);
        self::assertIsArray($row);

        return $row;
    }

    /** @return list<array<string, mixed>> */
    private function listTypeItems(): array
    {
        return self::rows($this->dicts->listTypes(null)['items']);
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
        $this->conn->insert('sand_system_plugin', ['code' => 'flowdemo', 'name' => '流程演示']);
        $this->addType('甲组系统', 1);
        $this->addType('甲组插件', 1, 'flowdemo.pet_colors');
        $this->addType('甲组残组', 1, 'ghost.colors');
        $this->addType('甲组自建', 0);
        $types = $this->listTypeItems();

        self::assertCount(4, $types);
        self::assertSame(['kind' => 'system', 'plugin_label' => null], $types[0]['source']);
        self::assertSame(['kind' => 'plugin', 'plugin_label' => '流程演示'], $types[1]['source']);
        self::assertSame(['kind' => 'plugin', 'plugin_label' => 'ghost'], $types[2]['source']);
        self::assertSame(['kind' => 'custom', 'plugin_label' => null], $types[3]['source']);
    }

    public function testListTypesCountsItems(): void
    {
        $type = $this->addType('乙组');
        $typeId = OrgSql::int($type['id'] ?? null);
        $this->addItem($typeId, '一');
        $this->addItem($typeId, '二');
        $items = $this->listTypeItems();

        self::assertSame(2, OrgSql::int($items[0]['item_count'] ?? null));
        self::assertSame('乙组', $items[0]['name']);
        self::assertStringStartsWith('dict_', OrgSql::str($items[0]['code'] ?? null));
    }

    public function testShowTypeMarksUsedFlag(): void
    {
        $type = $this->addType('丙组');
        $typeId = OrgSql::int($type['id'] ?? null);
        $used = $this->addItem($typeId, '已引用');
        $this->addItem($typeId, '未引用');
        $this->conn->insert('sand_system_dict_item_ref', ['item_id' => OrgSql::int($used['id'] ?? null)]);
        $pluginType = $this->addType('丙组插件', 1, 'ghost.k');
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
        $this->dicts->showType(999);
    }

    public function testCreateTypeWritesRowAndAudit(): void
    {
        $created = $this->dicts->createType(
            ['name' => '新分组', 'remark' => '备注一'],
            $this->actor,
            'req-1',
            null,
            null,
        );

        self::assertSame('新分组', $created['name']);
        self::assertStringStartsWith('dict_', OrgSql::str($created['code'] ?? null));
        self::assertSame('备注一', $created['remark']);
        self::assertFalse($created['is_system']);
        self::assertSame(['kind' => 'custom', 'plugin_label' => null], $created['source']);
        $audit = $this->conn->fetchAssociative('SELECT * FROM sand_system_audit_log');
        self::assertIsArray($audit);
        self::assertSame('dict', $audit['module']);
        self::assertSame('create', $audit['action']);
        self::assertSame('dict_type', $audit['target_type']);
        self::assertSame('7', OrgSql::str($audit['actor_id'] ?? null));
    }

    public function testCreateTypeValidatesInput(): void
    {
        try {
            $this->dicts->createType([], $this->actor, 'r', null, null);
            self::fail('缺 name 应抛异常');
        } catch (OrgException $e) {
            self::assertSame(422, $e->status);
            self::assertArrayHasKey('name', $e->fields);
        }
        try {
            $this->dicts->createType(['name' => str_repeat('很', 21)], $this->actor, 'r', null, null);
            self::fail('超长 name 应抛异常');
        } catch (DictException $e) {
            self::assertSame(422, $e->status);
            self::assertArrayHasKey('name', $e->fields);
        }
        try {
            $this->dicts->createType(['name' => '组', 'remark' => str_repeat('长', 101)], $this->actor, 'r', null, null);
            self::fail('超长 remark 应抛异常');
        } catch (DictException $e) {
            self::assertArrayHasKey('remark', $e->fields);
        }
    }

    public function testUpdateTypeChangesNameAndRemark(): void
    {
        $type = $this->addType('旧名');
        $saved = $this->dicts->updateType(
            OrgSql::int($type['id'] ?? null),
            ['name' => '新名', 'remark' => '改过'],
            $this->actor,
            'req-2',
            null,
            null,
        );

        self::assertSame('新名', $saved['name']);
        self::assertSame('改过', $saved['remark']);
        $audit = $this->conn->fetchAssociative(
            'SELECT changes FROM sand_system_audit_log WHERE action = ?',
            ['update'],
        );
        self::assertIsArray($audit);
        self::assertSame(
            ['name' => ['before' => '旧名', 'after' => '新名']],
            json_decode(OrgSql::str($audit['changes'] ?? null), true),
        );
    }

    public function testRemoveTypeDeletesCustom(): void
    {
        $type = $this->addType('能删的组');
        $id = OrgSql::int($type['id'] ?? null);
        $this->dicts->removeType($id, $this->actor, 'r', null, null);

        self::assertFalse(
            $this->conn->fetchOne('SELECT 1 FROM sand_system_dict_type WHERE id = ?', [$id]),
        );
    }

    public function testRemoveTypeRejectsSystemAndExtension(): void
    {
        $system = $this->addType('系统组', 1);
        $extension = $this->addType('插件组', 0, 'flowdemo.colors');

        foreach ([$system, $extension] as $row) {
            try {
                $this->dicts->removeType(OrgSql::int($row['id'] ?? null), $this->actor, 'r', null, null);
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
            ['label' => '甲选项', 'color' => '#3491FA', 'sort' => 3],
            $this->actor,
            'r',
            null,
            null,
        );

        self::assertSame('甲选项', $item['label']);
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
                $this->dicts->createItem($typeId, $body, $this->actor, 'r', null, null);
                self::fail("应抛 {$field} 校验异常");
            } catch (DictException $e) {
                self::assertSame(422, $e->status);
                self::assertArrayHasKey($field, $e->fields);
            }
        }
        try {
            $this->dicts->createItem($typeId, ['label' => '甲', 'sort' => 'abc'], $this->actor, 'r', null, null);
            self::fail('非法 sort 应抛异常');
        } catch (OrgException $e) {
            self::assertArrayHasKey('sort', $e->fields);
        }
    }

    public function testCreateItemClearsPreviousDefault(): void
    {
        $type = $this->addType('默认组');
        $typeId = OrgSql::int($type['id'] ?? null);
        $old = $this->addItem($typeId, '旧默认', 1);

        $this->dicts->createItem($typeId, ['label' => '新默认', 'is_default' => true], $this->actor, 'r', null, null);

        $items = $this->showItems($typeId);
        self::assertFalse($items[0]['is_default'], '旧默认项应被清掉');
        self::assertSame(OrgSql::str($old['id'] ?? null), $items[0]['id']);
    }

    public function testUpdateItemKeepsStatusWhenAbsent(): void
    {
        $type = $this->addType('戊组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '原选项');
        $itemId = OrgSql::int($item['id'] ?? null);
        $this->dicts->setItemStatus($itemId, 2, $this->actor, 'r', null, null);

        $saved = $this->dicts->updateItem(
            $itemId,
            ['label' => '改名', 'color' => '#13C2C2'],
            $this->actor,
            'r',
            null,
            null,
        );

        self::assertSame('改名', $saved['label']);
        self::assertSame('#13C2C2', $saved['color']);
        self::assertSame('disabled', $saved['status'], '请求没带 status 时应沿用库里的停用态');
    }

    public function testSetItemStatusToggles(): void
    {
        $type = $this->addType('己组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '甲');
        $itemId = OrgSql::int($item['id'] ?? null);

        $this->dicts->setItemStatus($itemId, 2, $this->actor, 'r', null, null);
        self::assertSame('disabled', $this->statusOf($itemId));
        $this->dicts->setItemStatus($itemId, 1, $this->actor, 'r', null, null);
        self::assertSame('enabled', $this->statusOf($itemId));
    }

    public function testRemoveItemRejectsSystemItem(): void
    {
        $type = $this->addType('庚组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '系统项', 0, 1);

        $this->expectException(DictException::class);
        $this->dicts->removeItem(OrgSql::int($item['id'] ?? null), $this->actor, 'r', null, null);
    }

    public function testRemoveItemDeletesCustomItem(): void
    {
        $type = $this->addType('辛组');
        $item = $this->addItem(OrgSql::int($type['id'] ?? null), '可删');
        $itemId = OrgSql::int($item['id'] ?? null);
        $this->dicts->removeItem($itemId, $this->actor, 'r', null, null);

        self::assertFalse(
            $this->conn->fetchOne('SELECT 1 FROM sand_system_dict_item WHERE id = ?', [$itemId]),
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
            'r',
            null,
            null,
        );

        $labels = array_column($this->showItems($typeId), 'label');
        self::assertSame(['丙', '甲', '乙'], $labels);
    }

    public function testMissingItemThrows(): void
    {
        $this->expectException(DictException::class);
        $this->dicts->updateItem(999, ['label' => 'x'], $this->actor, 'r', null, null);
    }

    private function statusOf(int $itemId): string
    {
        foreach ($this->listTypeItems() as $type) {
            foreach ($this->showItems(OrgSql::int($type['id'] ?? null)) as $item) {
                if (OrgSql::int($item['id'] ?? null) === $itemId) {
                    return OrgSql::str($item['status'] ?? null);
                }
            }
        }
        self::fail('选项不存在');
    }
}
