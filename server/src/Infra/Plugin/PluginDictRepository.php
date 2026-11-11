<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Domain\Plugin\OptionGroup;
use Sandadmin\Domain\Plugin\OptionItem;

/**
 * 插件自带选项组（option_group）与 sand_system_dict_type / sand_system_dict_item
 * 的同步仓储。表结构由 1.x 基线迁移 + 0002 extension_key 列定义，本类不建表。
 * extension_key 形如 <plugin_code>.<组key>，NULL 行为系统自带或管理员手工
 * 创建的组，同步与清除都不触碰。
 * 调用点：插件安装/升级完成后的字典同步、卸载勾「删除数据」的 purge 分支
 * （编排在 sandpackage，本仓暂无，接入时直接调本类两个公开方法）。
 */
final readonly class PluginDictRepository
{
    public const TYPE_TABLE = 'sand_system_dict_type';
    public const ITEM_TABLE = 'sand_system_dict_item';

    private const MAX_DICT_CODE = 64;
    private const MAX_DICT_NAME = 20;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * 把清单里启用的 option_group 物化为字典组：组按 extension_key upsert
     * （code 取 extension_key 同形、超长截断加哈希后缀；name 取组 title 截断
     * 到列宽），组内选项按 value upsert。清单里已删的组、组内已删的插件选项
     * 同步删除；管理员在插件组内手工补的非 is_system 选项保留。一个事务完成。
     *
     * @param list<OptionGroup> $groups
     */
    public function saveOptionGroups(string $pluginCode, array $groups, \DateTimeImmutable $at): void
    {
        $pluginCode = ExtensionRules::pluginCode($pluginCode);
        $at = $at->setTimezone(new \DateTimeZone('UTC'));
        $this->connection->transactional(function () use ($pluginCode, $groups, $at): void {
            $extensionKeys = [];
            foreach ($groups as $group) {
                if ($group->pluginCode !== $pluginCode) {
                    throw new InvalidExtensionException('option_group 不属于该插件', ['plugin_code' => 'mismatch']);
                }
                $extensionKey = $pluginCode . '.' . $group->key;
                $extensionKeys[] = $extensionKey;
                $typeId = $this->saveType($group->title, $extensionKey, $at);
                $this->saveItems($typeId, $group->options, $at);
            }
            $this->deleteStaleTypes($pluginCode, $extensionKeys);
        });
    }

    /**
     * 卸载勾「删除数据」时删除插件自带选项组，dict_item 由外键级联删除。
     * plugin_code 可含 '_'，用前缀等值比较而不是 LIKE，避免 '_' 被当成
     * 通配符误删其他插件的组；前缀带 '.' 结尾保证不误删更长编码的插件。
     *
     * @return int 删除的组数
     */
    public function purgeDicts(string $pluginCode): int
    {
        $prefix = ExtensionRules::pluginCode($pluginCode) . '.';
        $affectedRows = $this->queryBuilder()
            ->delete(self::TYPE_TABLE)
            ->where('substr(extension_key, 1, :prefix_len) = :prefix')
            ->setParameter('prefix_len', strlen($prefix), ParameterType::INTEGER)
            ->setParameter('prefix', $prefix)
            ->executeStatement();

        return (int) $affectedRows;
    }

    private function saveType(string $title, string $extensionKey, \DateTimeImmutable $at): int
    {
        $name = self::dictName($title);
        $existingId = $this->typeId($extensionKey);
        if ($existingId !== null) {
            $this->queryBuilder()
                ->update(self::TYPE_TABLE)
                ->set('name', ':name')
                ->set('update_time', ':update_time')
                ->where('id = :id')
                ->setParameter('name', $name)
                ->setParameter('update_time', $at, Types::DATETIME_IMMUTABLE)
                ->setParameter('id', $existingId, ParameterType::INTEGER)
                ->executeStatement();

            return $existingId;
        }

        // dict_type 无 extension_key 唯一约束，重查取回刚插入行的 identity id
        $id = $this->connection->executeQuery(
            'INSERT INTO ' . self::TYPE_TABLE
            . ' (code, name, remark, is_system, extension_key, create_time, update_time)'
            . ' VALUES (:code, :name, :remark, :is_system, :extension_key, :create_time, :update_time)'
            . ' RETURNING id',
            [
                'code' => self::dictCode($extensionKey),
                'name' => $name,
                'remark' => '插件自带选项组',
                'is_system' => true,
                'extension_key' => $extensionKey,
                'create_time' => $at,
                'update_time' => $at,
            ],
            [
                'is_system' => Types::BOOLEAN,
                'create_time' => Types::DATETIME_IMMUTABLE,
                'update_time' => Types::DATETIME_IMMUTABLE,
            ],
        )->fetchOne();

        return $this->asInt($id, 'id');
    }

    /** @param list<OptionItem> $options */
    private function saveItems(int $typeId, array $options, \DateTimeImmutable $at): void
    {
        $values = [];
        foreach ($options as $position => $option) {
            $values[] = $option->value;
            $existingId = $this->itemId($typeId, $option->value);
            if ($existingId !== null) {
                $this->queryBuilder()
                    ->update(self::ITEM_TABLE)
                    ->set('label', ':label')
                    ->set($this->connection->quoteSingleIdentifier('sort'), ':sort')
                    ->set('update_time', ':update_time')
                    ->where('id = :id')
                    ->setParameter('label', self::itemLabel($option->label))
                    ->setParameter('sort', $position, ParameterType::INTEGER)
                    ->setParameter('update_time', $at, Types::DATETIME_IMMUTABLE)
                    ->setParameter('id', $existingId, ParameterType::INTEGER)
                    ->executeStatement();
                continue;
            }
            $this->queryBuilder()
                ->insert(self::ITEM_TABLE)
                ->values([
                    'type_id' => ':type_id',
                    'label' => ':label',
                    'value' => ':value',
                    'is_system' => ':is_system',
                    'status' => ':status',
                    $this->connection->quoteSingleIdentifier('sort') => ':sort',
                    'create_time' => ':create_time',
                    'update_time' => ':update_time',
                ])
                ->setParameter('type_id', $typeId, ParameterType::INTEGER)
                ->setParameter('label', self::itemLabel($option->label))
                ->setParameter('value', $option->value)
                ->setParameter('is_system', true, Types::BOOLEAN)
                ->setParameter('status', 1, ParameterType::INTEGER)
                ->setParameter('sort', $position, ParameterType::INTEGER)
                ->setParameter('create_time', $at, Types::DATETIME_IMMUTABLE)
                ->setParameter('update_time', $at, Types::DATETIME_IMMUTABLE)
                ->executeStatement();
        }

        // 清单删掉的插件选项同步删除；管理员手工补的（is_system=false）保留
        $delete = $this->queryBuilder()
            ->delete(self::ITEM_TABLE)
            ->where('type_id = :type_id')
            ->andWhere('is_system')
            ->setParameter('type_id', $typeId, ParameterType::INTEGER);
        if ($values !== []) {
            $delete->andWhere('value NOT IN (:values)')
                ->setParameter('values', $values, ArrayParameterType::STRING);
        }
        $delete->executeStatement();
    }

    /** @param list<string> $keep 本次清单仍在的 extension_key；空表表示该插件不再声明选项组 */
    private function deleteStaleTypes(string $pluginCode, array $keep): void
    {
        $prefix = $pluginCode . '.';
        $delete = $this->queryBuilder()
            ->delete(self::TYPE_TABLE)
            ->where('substr(extension_key, 1, :prefix_len) = :prefix')
            ->setParameter('prefix_len', strlen($prefix), ParameterType::INTEGER)
            ->setParameter('prefix', $prefix);
        if ($keep !== []) {
            $delete->andWhere('extension_key NOT IN (:keep)')
                ->setParameter('keep', $keep, ArrayParameterType::STRING);
        }
        $delete->executeStatement();
    }

    private function typeId(string $extensionKey): ?int
    {
        $value = $this->queryBuilder()
            ->select('id')
            ->from(self::TYPE_TABLE)
            ->where('extension_key = :extension_key')
            ->setParameter('extension_key', $extensionKey)
            ->executeQuery()
            ->fetchOne();
        if ($value === false) {
            return null;
        }

        return $this->asInt($value, 'id');
    }

    private function itemId(int $typeId, string $value): ?int
    {
        $found = $this->queryBuilder()
            ->select('id')
            ->from(self::ITEM_TABLE)
            ->where('type_id = :type_id')
            ->andWhere('value = :value')
            ->setParameter('type_id', $typeId, ParameterType::INTEGER)
            ->setParameter('value', $value)
            ->executeQuery()
            ->fetchOne();
        if ($found === false) {
            return null;
        }

        return $this->asInt($found, 'id');
    }

    /**
     * dict_type.code 只有 64 宽，extension_key 可达 128；截断后拼哈希后缀，
     * 同一 extension_key 得到确定且基本不撞的 code。
     */
    private static function dictCode(string $extensionKey): string
    {
        if (strlen($extensionKey) <= self::MAX_DICT_CODE) {
            return $extensionKey;
        }

        return substr($extensionKey, 0, self::MAX_DICT_CODE - 9)
            . '.' . substr(hash('sha256', $extensionKey), 0, 8);
    }

    /** dict_type.name 只有 20 字符宽；超长截断，重名由 uk_sand_system_dict_type_name 暴露。 */
    private static function dictName(string $title): string
    {
        return mb_substr($title, 0, self::MAX_DICT_NAME);
    }

    /** dict_item.label 只有 20 字符宽，同 name 口径截断。 */
    private static function itemLabel(string $label): string
    {
        return mb_substr($label, 0, self::MAX_DICT_NAME);
    }

    private function asInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidExtensionException('列类型无效', [$field => 'int']);
    }

    private function queryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }
}
