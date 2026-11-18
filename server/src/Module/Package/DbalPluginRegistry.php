<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Package;

use Doctrine\DBAL\Connection;
use Sandadmin\Contract\KernelApi\KernelApiVersion;

/**
 * sand_system_plugin 的 DBAL 账本。SQL 口径与 PackageOps::apply 一致：
 * 行存在就更新否则插入，state 只写 installed/uninstalled；
 * host_constraint 记安装时宿主的 kernel_api 版本。
 */
final class DbalPluginRegistry implements PluginRegistry
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function installedVersion(string $app): ?string
    {
        $version = $this->connection->fetchOne(
            'SELECT version FROM sand_system_plugin WHERE code = ? AND state = \'installed\'',
            [$app],
        );

        return is_string($version) ? $version : null;
    }

    /** @return array<string, string> */
    public function installedVersions(): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT code, version FROM sand_system_plugin WHERE state = \'installed\'',
        );
        $versions = [];
        foreach ($rows as $app => $version) {
            if (is_string($version)) {
                $versions[(string) $app] = $version;
            }
        }

        return $versions;
    }

    public function recordInstall(string $app, string $name, string $version, string $manifestSha256): void
    {
        $kept = $this->connection->fetchOne('SELECT id FROM sand_system_plugin WHERE code = ?', [$app]);
        if ($kept === false) {
            $this->connection->executeStatement(
                'INSERT INTO sand_system_plugin (code, name, version, host_constraint, manifest_sha256, state)'
                . ' VALUES (:code, :name, :version, :host, :sha, \'installed\')',
                [
                    'code' => $app,
                    'name' => $name,
                    'version' => $version,
                    'host' => KernelApiVersion::CURRENT,
                    'sha' => $manifestSha256,
                ],
            );

            return;
        }
        // 不删数据的卸载留 uninstalled 行，再装只更新，不插新行
        $this->connection->executeStatement(
            'UPDATE sand_system_plugin SET name = :name, version = :version, manifest_sha256 = :sha,'
            . ' state = \'installed\', uninstall_time = NULL, update_time = CURRENT_TIMESTAMP WHERE code = :code',
            [
                'name' => $name,
                'version' => $version,
                'sha' => $manifestSha256,
                'code' => $app,
            ],
        );
    }

    public function recordUninstall(string $app, bool $purge): void
    {
        if ($purge) {
            $this->connection->executeStatement('DELETE FROM sand_system_plugin WHERE code = ?', [$app]);

            return;
        }
        $this->connection->executeStatement(
            'UPDATE sand_system_plugin SET state = \'uninstalled\', uninstall_time = CURRENT_TIMESTAMP,'
            . ' update_time = CURRENT_TIMESTAMP WHERE code = ?',
            [$app],
        );
    }
}
