<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Sandadmin\Domain\Plugin\ExtensionKind;
use Sandadmin\Domain\Plugin\ExtensionOverride;
use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\InvalidExtensionException;

/**
 * sand_system_plugin_extension 仓储：只存管理员对扩展位的启停与排序覆盖。
 * 表结构由 1.x 基线迁移定义，本类不建表；每次调用新建 QueryBuilder。
 */
final readonly class PluginExtensionRepository
{
    public const TABLE = 'sand_system_plugin_extension';

    public function __construct(private Connection $connection)
    {
    }

    /** 写入或更新一条覆盖；create_time 只在首次插入时写入。 */
    public function save(ExtensionOverride $override, \DateTimeImmutable $at): void
    {
        $at = $at->setTimezone(new \DateTimeZone('UTC'));
        $this->connection->transactional(function () use ($override, $at): void {
            $affectedRows = $this->queryBuilder()
                ->update(self::TABLE)
                ->set('enabled', ':enabled')
                ->set('sort', ':sort')
                ->set('update_time', ':update_time')
                ->where($this->connection->quoteSingleIdentifier('plugin_code') . ' = :plugin_code')
                ->andWhere($this->connection->quoteSingleIdentifier('kind') . ' = :kind')
                ->andWhere($this->connection->quoteSingleIdentifier('extension_key') . ' = :extension_key')
                ->setParameter('plugin_code', $override->pluginCode)
                ->setParameter('kind', $override->kind->value)
                ->setParameter('extension_key', $override->extensionKey)
                ->setParameter('enabled', $override->enabled, Types::BOOLEAN)
                ->setParameter('sort', $override->sort, ParameterType::INTEGER)
                ->setParameter('update_time', $at, Types::DATETIME_IMMUTABLE)
                ->executeStatement();
            if ($affectedRows > 0) {
                return;
            }
            $this->queryBuilder()
                ->insert(self::TABLE)
                ->values([
                    'plugin_code' => ':plugin_code',
                    'kind' => ':kind',
                    'extension_key' => ':extension_key',
                    'enabled' => ':enabled',
                    'sort' => ':sort',
                    'create_time' => ':create_time',
                    'update_time' => ':update_time',
                ])
                ->setParameter('plugin_code', $override->pluginCode)
                ->setParameter('kind', $override->kind->value)
                ->setParameter('extension_key', $override->extensionKey)
                ->setParameter('enabled', $override->enabled, Types::BOOLEAN)
                ->setParameter('sort', $override->sort, ParameterType::INTEGER)
                ->setParameter('create_time', $at, Types::DATETIME_IMMUTABLE)
                ->setParameter('update_time', $at, Types::DATETIME_IMMUTABLE)
                ->executeStatement();
        });
    }

    /** 删除一条覆盖；扩展回到清单默认。目标不存在时不报错。 */
    public function delete(string $pluginCode, ExtensionKind $kind, string $extensionKey): void
    {
        $this->queryBuilder()
            ->delete(self::TABLE)
            ->where($this->connection->quoteSingleIdentifier('plugin_code') . ' = :plugin_code')
            ->andWhere($this->connection->quoteSingleIdentifier('kind') . ' = :kind')
            ->andWhere($this->connection->quoteSingleIdentifier('extension_key') . ' = :extension_key')
            ->setParameter('plugin_code', ExtensionRules::pluginCode($pluginCode))
            ->setParameter('kind', $kind->value)
            ->setParameter('extension_key', ExtensionRules::key($extensionKey))
            ->executeStatement();
    }

    /** 删除某插件的全部覆盖行；插件行被物理删除时由外键级联，本方法供显式清理。 */
    public function deleteForPlugin(string $pluginCode): void
    {
        $this->queryBuilder()
            ->delete(self::TABLE)
            ->where($this->connection->quoteSingleIdentifier('plugin_code') . ' = :plugin_code')
            ->setParameter('plugin_code', ExtensionRules::pluginCode($pluginCode))
            ->executeStatement();
    }

    /** @return list<ExtensionOverride> */
    public function listByKind(ExtensionKind $kind): array
    {
        $rows = $this->queryBuilder()
            ->select('plugin_code', 'kind', 'extension_key', 'enabled', 'sort')
            ->from(self::TABLE)
            ->where($this->connection->quoteSingleIdentifier('kind') . ' = :kind')
            ->setParameter('kind', $kind->value)
            ->executeQuery()
            ->fetchAllAssociative();

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function countForPlugin(string $pluginCode): int
    {
        $pluginCode = ExtensionRules::pluginCode($pluginCode);
        $value = $this->queryBuilder()
            ->select('COUNT(*) AS n')
            ->from(self::TABLE)
            ->where($this->connection->quoteSingleIdentifier('plugin_code') . ' = :plugin_code')
            ->setParameter('plugin_code', $pluginCode)
            ->executeQuery()
            ->fetchOne();

        return $this->asInt($value, 'n');
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ExtensionOverride
    {
        $kindRaw = $this->asString($row['kind'] ?? null, 'kind');
        $kind = ExtensionKind::tryFrom($kindRaw);
        if ($kind === null) {
            throw new InvalidExtensionException('未知扩展类型', ['kind' => 'enum']);
        }

        return new ExtensionOverride(
            $this->asString($row['plugin_code'] ?? null, 'plugin_code'),
            $kind,
            $this->asString($row['extension_key'] ?? null, 'extension_key'),
            $this->asBool($row['enabled'] ?? null, 'enabled'),
            $this->asInt($row['sort'] ?? null, 'sort'),
        );
    }

    private function asString(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new InvalidExtensionException('列类型无效', [$field => 'string']);
        }

        return $value;
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

    private function asBool(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 't' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'f' || $value === 'false') {
            return false;
        }

        throw new InvalidExtensionException('列类型无效', [$field => 'bool']);
    }

    private function queryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }
}
