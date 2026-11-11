<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Plugin\OptionGroup;
use Sandadmin\Domain\Plugin\OptionItem;
use Sandadmin\Infra\Plugin\PluginDictRepository;

/**
 * 只在 PostgreSQL 上跑（RETURNING、FK CASCADE、timestamptz），
 * 设置 SAND_TEST_DB_URL 才执行；要求库内已应用 0001 基线 + 0002 extension_key。
 * 用例间共享库，每个用例清空字典两表。
 */
final class PluginDictRepositoryTest extends TestCase
{
    private Connection $conn;
    private PluginDictRepository $repo;

    protected function setUp(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向 PostgreSQL');
        }
        $this->conn = DriverManager::getConnection((new DsnParser())->parse($url));
        // 删组即级联删选项
        $this->conn->executeStatement('DELETE FROM ' . PluginDictRepository::TYPE_TABLE);
        $this->repo = new PluginDictRepository($this->conn);
    }

    protected function tearDown(): void
    {
        if (!isset($this->conn)) {
            return;
        }
        $this->conn->close();
    }

    private static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-10 08:00:00', new \DateTimeZone('UTC'));
    }

    /** @return array<string, mixed>|null */
    private function typeRow(string $extensionKey): ?array
    {
        $row = $this->conn->fetchAssociative(
            'SELECT id, code, name, remark, is_system, extension_key FROM '
            . PluginDictRepository::TYPE_TABLE . ' WHERE extension_key = ?',
            [$extensionKey],
        );

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    private function itemRows(int $typeId): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT label, value, is_system, status, sort FROM ' . PluginDictRepository::ITEM_TABLE
            . ' WHERE type_id = ? ORDER BY sort, id',
            [$typeId],
        );
    }

    /** @param array<string, mixed> $row */
    private static function rowInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            return (int) $value;
        }
        self::fail('列 ' . $key . ' 不是整数');
    }

    /** @param array<string, mixed> $row */
    private static function rowBool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;

        return $value === true || $value === 1 || $value === '1' || $value === 't';
    }

    private function countRows(string $sql): int
    {
        $value = $this->conn->fetchOne($sql);
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            return (int) $value;
        }
        self::fail('COUNT 返回非整数');
    }

    public function testSaveOptionGroupsMaterializesAndSyncs(): void
    {
        $this->repo->saveOptionGroups('acme', [
            new OptionGroup('acme', 'status', '状态', [
                new OptionItem('on', '开启'),
                new OptionItem('off', '关闭'),
            ], 0),
        ], self::at());

        $type = $this->typeRow('acme.status');
        self::assertNotNull($type);
        self::assertSame('acme.status', $type['code']);
        self::assertSame('状态', $type['name']);
        self::assertTrue(self::rowBool($type, 'is_system'));
        $typeId = self::rowInt($type, 'id');

        $items = $this->itemRows($typeId);
        self::assertCount(2, $items);
        self::assertSame('开启', $items[0]['label']);
        self::assertSame('on', $items[0]['value']);
        self::assertTrue(self::rowBool($items[0], 'is_system'));

        // 再同步：改标题、改选项 label、删掉 off、新增 mid——是更新不是重复插入
        $this->repo->saveOptionGroups('acme', [
            new OptionGroup('acme', 'status', '状态新', [
                new OptionItem('mid', '中间'),
                new OptionItem('on', '开'),
            ], 0),
        ], self::at()->modify('+1 hour'));

        $type = $this->typeRow('acme.status');
        self::assertNotNull($type);
        self::assertSame($typeId, self::rowInt($type, 'id'));
        self::assertSame('状态新', $type['name']);
        self::assertSame(
            1,
            $this->countRows(
                'SELECT COUNT(*) FROM ' . PluginDictRepository::TYPE_TABLE . " WHERE extension_key = 'acme.status'",
            ),
        );

        $items = $this->itemRows($typeId);
        self::assertSame(['mid', 'on'], array_column($items, 'value'));
        self::assertSame(['中间', '开'], array_column($items, 'label'));
    }

    public function testManualItemSurvivesSync(): void
    {
        $this->repo->saveOptionGroups('acme', [
            new OptionGroup('acme', 'color', '颜色', [new OptionItem('red', '红')], 0),
        ], self::at());
        $type = $this->typeRow('acme.color');
        self::assertNotNull($type);
        $typeId = self::rowInt($type, 'id');
        // 管理员在插件组内手工补的选项不是 is_system，同步保留
        $this->conn->executeStatement(
            'INSERT INTO ' . PluginDictRepository::ITEM_TABLE
            . ' (type_id, label, value, is_system, status, sort) VALUES (?, ?, ?, false, 1, 9)',
            [$typeId, '手工项', 'manual'],
        );

        $this->repo->saveOptionGroups('acme', [
            new OptionGroup('acme', 'color', '颜色', [new OptionItem('blue', '蓝')], 0),
        ], self::at());

        $values = array_column($this->itemRows($typeId), 'value');
        sort($values);
        self::assertSame(['blue', 'manual'], $values);
    }

    public function testSyncRemovesUndeclaredGroups(): void
    {
        $this->repo->saveOptionGroups('acme', [
            new OptionGroup('acme', 'one', '一', [new OptionItem('a', '甲')], 0),
            new OptionGroup('acme', 'two', '二', [new OptionItem('b', '乙')], 0),
        ], self::at());
        self::assertNotNull($this->typeRow('acme.two'));

        // 清单只剩 one：two 整组连同选项删除，手工组（extension_key NULL）不动
        $this->conn->executeStatement(
            'INSERT INTO ' . PluginDictRepository::TYPE_TABLE . " (code, name, is_system) VALUES ('manual_dict', '手工组', false)",
        );
        $this->repo->saveOptionGroups('acme', [
            new OptionGroup('acme', 'one', '一', [new OptionItem('a', '甲')], 0),
        ], self::at());

        self::assertNull($this->typeRow('acme.two'));
        self::assertSame(
            0,
            $this->countRows(
                'SELECT COUNT(*) FROM ' . PluginDictRepository::ITEM_TABLE . ' i'
                . ' JOIN ' . PluginDictRepository::TYPE_TABLE . ' t ON i.type_id = t.id'
                . " WHERE t.extension_key = 'acme.two'",
            ),
        );
        self::assertSame(
            1,
            $this->countRows(
                'SELECT COUNT(*) FROM ' . PluginDictRepository::TYPE_TABLE . " WHERE code = 'manual_dict'",
            ),
        );
    }

    public function testPurgeDictsRespectsCodePrefixExactly(): void
    {
        // plugin_code 可含 '_'，LIKE 会把 '_' 当通配符；前缀等值比较必须只删目标插件
        $this->repo->saveOptionGroups('sand_iam', [
            new OptionGroup('sand_iam', 'g1', '组一', [new OptionItem('x', '项')], 0),
        ], self::at());
        $this->repo->saveOptionGroups('sand-iam', [
            new OptionGroup('sand-iam', 'g1', '组二', [new OptionItem('y', '项')], 0),
        ], self::at());

        self::assertSame(1, $this->repo->purgeDicts('sand_iam'));
        self::assertNull($this->typeRow('sand_iam.g1'));
        self::assertNotNull($this->typeRow('sand-iam.g1'));

        // 目标插件没有组时返回 0，不报错
        self::assertSame(0, $this->repo->purgeDicts('sand_iam'));
        self::assertSame(1, $this->repo->purgeDicts('sand-iam'));
    }

    public function testMismatchedGroupPluginCodeRejected(): void
    {
        try {
            $this->repo->saveOptionGroups('acme', [
                new OptionGroup('other', 'g', '组', [new OptionItem('x', '项')], 0),
            ], self::at());
            self::fail('别的插件的组必须拒绝');
        } catch (\DomainException) {
        }
        self::assertNull($this->typeRow('other.g'));
    }
}
