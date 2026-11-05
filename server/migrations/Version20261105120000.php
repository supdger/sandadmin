<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Sandadmin\Infra\Plugin\Schema\PluginExtensionSchema;

/**
 * 创建 plugin_extension。插件安装时写入，运行时只读，不扫目录。
 */
final class Version20261105120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '创建 plugin_extension（插件扩展位注册表）';
    }

    public function up(Schema $schema): void
    {
        PluginExtensionSchema::addToSchema($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable(PluginExtensionSchema::TABLE)) {
            $schema->dropTable(PluginExtensionSchema::TABLE);
        }
    }
}
