<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/** plugin_extension 的唯一表结构定义。迁移与测试都走这里，避免两套 SQL。 */
final class PluginExtensionSchema
{
    public const TABLE = 'plugin_extension';

    private function __construct()
    {
    }

    public static function addToSchema(Schema $schema): void
    {
        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $table = $schema->createTable(self::TABLE);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('plugin_id', 'string', ['length' => 64]);
        $table->addColumn('kind', 'string', ['length' => 64]);
        $table->addColumn('extension_key', 'string', ['length' => 128]);
        $table->addColumn('title', 'string', ['length' => 200]);
        $table->addColumn('payload', 'json');
        $table->addColumn('enabled', 'boolean', ['default' => true]);
        $table->addColumn('sort_order', 'integer', ['default' => 0]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['plugin_id', 'kind', 'extension_key'], 'plugin_extension_identity');
        $table->addIndex(['kind', 'enabled', 'sort_order'], 'plugin_extension_lookup');
    }

    public static function apply(Connection $connection): void
    {
        $schema = new Schema();
        self::addToSchema($schema);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }
}
