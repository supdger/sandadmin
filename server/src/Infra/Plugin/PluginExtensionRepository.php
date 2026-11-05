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
use Sandadmin\Domain\Plugin\ExtensionRules;
use Sandadmin\Domain\Plugin\InvalidExtensionException;
use Sandadmin\Infra\Plugin\Schema\PluginExtensionSchema;

/**
 * plugin_extension 表仓储。每次调用新建 QueryBuilder，不缓存查询结果。
 */
final readonly class PluginExtensionRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * 用本次安装声明整体替换该插件的扩展行。校验失败不得调用本方法。
     *
     * @param list<StoredExtension> $rows
     */
    public function replacePlugin(string $pluginId, array $rows, \DateTimeImmutable $at): void
    {
        $pluginId = ExtensionRules::pluginId($pluginId);
        foreach ($rows as $row) {
            if ($row->pluginId !== $pluginId) {
                throw new InvalidExtensionException('plugin_id 不一致', ['plugin_id' => 'mismatch']);
            }
        }
        $at = $at->setTimezone(new \DateTimeZone('UTC'));
        $this->connection->transactional(function () use ($pluginId, $rows, $at): void {
            $this->deleteRows($pluginId);
            foreach ($rows as $row) {
                $this->insert($row, $at);
            }
        });
    }

    public function deletePlugin(string $pluginId): void
    {
        $this->deleteRows(ExtensionRules::pluginId($pluginId));
    }

    /** @return list<StoredExtension> */
    public function listEnabled(ExtensionKind $kind): array
    {
        $rows = $this->qb()
            ->select('plugin_id', 'kind', 'extension_key', 'title', 'sort_order', 'enabled', 'payload')
            ->from(PluginExtensionSchema::TABLE)
            ->where($this->connection->quoteSingleIdentifier('kind') . ' = :kind')
            ->andWhere($this->connection->quoteSingleIdentifier('enabled') . ' = :enabled')
            ->setParameter('kind', $kind->value)
            ->setParameter('enabled', true, Types::BOOLEAN)
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('plugin_id', 'ASC')
            ->addOrderBy('extension_key', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function countForPlugin(string $pluginId): int
    {
        $pluginId = ExtensionRules::pluginId($pluginId);
        $value = $this->qb()
            ->select('COUNT(*) AS n')
            ->from(PluginExtensionSchema::TABLE)
            ->where($this->connection->quoteSingleIdentifier('plugin_id') . ' = :plugin_id')
            ->setParameter('plugin_id', $pluginId)
            ->executeQuery()
            ->fetchOne();

        return $this->asInt($value, 'n');
    }

    private function deleteRows(string $pluginId): void
    {
        $this->qb()
            ->delete(PluginExtensionSchema::TABLE)
            ->where($this->connection->quoteSingleIdentifier('plugin_id') . ' = :plugin_id')
            ->setParameter('plugin_id', $pluginId)
            ->executeStatement();
    }

    private function insert(StoredExtension $row, \DateTimeImmutable $at): void
    {
        $this->qb()
            ->insert(PluginExtensionSchema::TABLE)
            ->values([
                'plugin_id' => ':plugin_id',
                'kind' => ':kind',
                'extension_key' => ':extension_key',
                'title' => ':title',
                'payload' => ':payload',
                'enabled' => ':enabled',
                'sort_order' => ':sort_order',
                'created_at' => ':created_at',
                'updated_at' => ':updated_at',
            ])
            ->setParameter('plugin_id', $row->pluginId)
            ->setParameter('kind', $row->kind->value)
            ->setParameter('extension_key', $row->key)
            ->setParameter('title', $row->title)
            ->setParameter('payload', $row->payload, Types::JSON)
            ->setParameter('enabled', $row->enabled, Types::BOOLEAN)
            ->setParameter('sort_order', $row->sortOrder, ParameterType::INTEGER)
            ->setParameter('created_at', $at, Types::DATETIME_IMMUTABLE)
            ->setParameter('updated_at', $at, Types::DATETIME_IMMUTABLE)
            ->executeStatement();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): StoredExtension
    {
        $kindRaw = $this->asString($row['kind'] ?? null, 'kind');
        $kind = ExtensionKind::tryFrom($kindRaw);
        if ($kind === null) {
            throw new InvalidExtensionException('未知扩展类型', ['kind' => 'enum']);
        }

        return new StoredExtension(
            $this->asString($row['plugin_id'] ?? null, 'plugin_id'),
            $kind,
            $this->asString($row['extension_key'] ?? null, 'extension_key'),
            $this->asString($row['title'] ?? null, 'title'),
            $this->asInt($row['sort_order'] ?? null, 'sort_order'),
            $this->asBool($row['enabled'] ?? null, 'enabled'),
            $this->payload($row['payload'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    private function payload(mixed $raw): array
    {
        if (is_string($raw)) {
            try {
                $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidExtensionException('扩展载荷不是合法 JSON', ['payload' => 'json'], $e);
            }
        }
        if (!is_array($raw)) {
            throw new InvalidExtensionException('扩展载荷必须为对象', ['payload' => 'object']);
        }
        $payload = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidExtensionException('扩展载荷必须为对象', ['payload' => 'object']);
            }
            $payload[$key] = $value;
        }

        return $payload;
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

    private function qb(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }
}
