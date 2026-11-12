<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Sandadmin\Domain\Plugin\ExtensionRules;

/**
 * 卸载插件且不删数据时，停用定时任务和扩展位覆盖。
 *
 * 不删 schedule 行：执行记录外键会级联删掉历史。
 * suspend_time 非空即停用；重新安装时清空，enabled 与排序保留。
 * 插件行的 state 仍由卸载流程自己写。
 */
final readonly class PluginResidue
{
    public function __construct(private Connection $connection)
    {
    }

    public function suspend(string $pluginCode, \DateTimeImmutable $at): void
    {
        $pluginCode = ExtensionRules::pluginCode($pluginCode);
        $at = $at->setTimezone(new \DateTimeZone('UTC'));
        $this->connection->transactional(function () use ($pluginCode, $at): void {
            $this->connection->executeStatement(
                'UPDATE ' . ScheduleClaim::TABLE . ' SET suspend_time = ?, update_time = ?'
                . ' WHERE plugin_code = ? AND suspend_time IS NULL',
                [$at, $at, $pluginCode],
                [Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE, ParameterType::STRING],
            );
            $this->connection->executeStatement(
                'UPDATE ' . PluginExtensionRepository::TABLE . ' SET suspend_time = ?, update_time = ?'
                . ' WHERE plugin_code = ? AND suspend_time IS NULL',
                [$at, $at, $pluginCode],
                [Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE, ParameterType::STRING],
            );
        });
    }

    public function resume(string $pluginCode, \DateTimeImmutable $at): void
    {
        $pluginCode = ExtensionRules::pluginCode($pluginCode);
        $at = $at->setTimezone(new \DateTimeZone('UTC'));
        $this->connection->transactional(function () use ($pluginCode, $at): void {
            $this->connection->executeStatement(
                'UPDATE ' . ScheduleClaim::TABLE . ' SET suspend_time = NULL, update_time = ? WHERE plugin_code = ?',
                [$at, $pluginCode],
                [Types::DATETIME_IMMUTABLE, ParameterType::STRING],
            );
            $this->connection->executeStatement(
                'UPDATE ' . PluginExtensionRepository::TABLE . ' SET suspend_time = NULL, update_time = ? WHERE plugin_code = ?',
                [$at, $pluginCode],
                [Types::DATETIME_IMMUTABLE, ParameterType::STRING],
            );
        });
    }
}
