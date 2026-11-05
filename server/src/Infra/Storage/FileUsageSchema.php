<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Sandadmin\Storage\Exception\StorageException;

/** 建表与 database/migrations/20261105120000_create_file_usage.sql 对齐。请求路径不执行。 */
final class FileUsageSchema
{
    public static function apply(Connection $connection): void
    {
        try {
            $manager = $connection->createSchemaManager();
            if ($manager->tablesExist(['file_usage'])) {
                return;
            }
            $schema = new Schema();
            $table = $schema->createTable('file_usage');
            $table->addColumn('file_id', 'string', ['length' => 32, 'notnull' => true]);
            $table->addColumn('subject_type', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('subject_id', 'string', ['length' => 64, 'notnull' => true]);
            $table->addColumn('created_at', 'string', ['length' => 40, 'notnull' => true]);
            $table->setPrimaryKey(['file_id', 'subject_type', 'subject_id']);
            foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
                $connection->executeStatement($sql);
            }
        } catch (\Throwable $error) {
            throw new StorageException('storage_unavailable', '无法准备文件引用表', [], $error);
        }
    }
}
