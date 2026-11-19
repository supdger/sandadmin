<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Package;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Package\DbalPluginRegistry;

/**
 * DBAL 账本与 PluginResidue 的联动：keep 卸载 suspend 定时任务与
 * 扩展位覆盖，安装（含重装）resume，purge 只删行——与网页通道
 * PackageOps::apply 同一口径。sqlite 内存库执行真实 SQL；
 * PostgreSQL/MySQL 双库矩阵的端到端用例在 Integration 套件。
 */
final class DbalPluginRegistryTest extends TestCase
{
    private const APP = 'sand-hello';

    private Connection $conn;

    private DbalPluginRegistry $registry;

    protected function setUp(): void
    {
        $this->conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach ([
            'CREATE TABLE sand_system_plugin ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' code VARCHAR(64) NOT NULL UNIQUE,'
            . ' name VARCHAR(64) NOT NULL,'
            . ' version VARCHAR(32) NOT NULL,'
            . ' host_constraint VARCHAR(32),'
            . ' manifest_sha256 VARCHAR(64),'
            . ' state VARCHAR(16) NOT NULL,'
            . ' uninstall_time DATETIME,'
            . ' update_time DATETIME)',
            'CREATE TABLE sand_system_schedule ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' code VARCHAR(128) NOT NULL,'
            . ' plugin_code VARCHAR(64),'
            . ' suspend_time DATETIME,'
            . ' update_time DATETIME)',
            'CREATE TABLE sand_system_plugin_extension ('
            . ' id INTEGER PRIMARY KEY AUTOINCREMENT,'
            . ' plugin_code VARCHAR(64),'
            . ' suspend_time DATETIME,'
            . ' update_time DATETIME)',
        ] as $sql) {
            $this->conn->executeStatement($sql);
        }
        $this->registry = new DbalPluginRegistry($this->conn);
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    public function testRecordInstallInsertsInstalledRow(): void
    {
        $this->registry->recordInstall(self::APP, 'Hello', '0.1.0', str_repeat('a', 64));

        self::assertSame('0.1.0', $this->registry->installedVersion(self::APP));
        self::assertSame(['sand-hello' => '0.1.0'], $this->registry->installedVersions());
        self::assertSame('installed', $this->pluginState());
    }

    public function testRecordUninstallSuspendsResidue(): void
    {
        $this->registry->recordInstall(self::APP, 'Hello', '0.1.0', 'x');
        $this->seedResidue(self::APP);

        $this->registry->recordUninstall(self::APP, false);

        self::assertSame('uninstalled', $this->pluginState());
        self::assertNotNull($this->conn->fetchOne(
            'SELECT uninstall_time FROM sand_system_plugin WHERE code = ?',
            [self::APP],
        ));
        self::assertSame(2, $this->suspendedResidueRows());
    }

    public function testRecordInstallResumesResidue(): void
    {
        $this->registry->recordInstall(self::APP, 'Hello', '0.1.0', 'x');
        $this->seedResidue(self::APP);
        $this->registry->recordUninstall(self::APP, false);
        self::assertSame(2, $this->suspendedResidueRows());

        $this->registry->recordInstall(self::APP, 'Hello', '0.2.0', 'y');

        self::assertSame('installed', $this->pluginState());
        self::assertSame('0.2.0', $this->registry->installedVersion(self::APP));
        self::assertSame(0, $this->suspendedResidueRows());
    }

    public function testRecordUninstallPurgeDeletesRow(): void
    {
        $this->registry->recordInstall(self::APP, 'Hello', '0.1.0', 'x');
        $this->seedResidue(self::APP);

        $this->registry->recordUninstall(self::APP, true);

        self::assertFalse($this->conn->fetchOne(
            'SELECT id FROM sand_system_plugin WHERE code = ?',
            [self::APP],
        ));
        // purge 不 suspend：真实 schema 靠级联删除残留行
        self::assertSame(0, $this->suspendedResidueRows());
    }

    public function testSuspendFailureRollsBackLedgerRow(): void
    {
        // 账本行与残留 suspend 同一事务：残留写失败时行不能停在 uninstalled
        $this->registry->recordInstall(self::APP, 'Hello', '0.1.0', 'x');
        $this->conn->executeStatement('DROP TABLE sand_system_plugin_extension');

        $thrown = false;
        try {
            $this->registry->recordUninstall(self::APP, false);
        } catch (\Throwable) {
            $thrown = true;
        }

        self::assertTrue($thrown, 'recordUninstall 应当在残留表缺失时抛错');
        self::assertSame('installed', $this->pluginState());
    }

    private function seedResidue(string $app): void
    {
        $this->conn->executeStatement(
            'INSERT INTO sand_system_schedule (code, plugin_code) VALUES (?, ?)',
            [$app . '.tick', $app],
        );
        $this->conn->executeStatement(
            'INSERT INTO sand_system_plugin_extension (plugin_code) VALUES (?)',
            [$app],
        );
    }

    private function pluginState(): ?string
    {
        $state = $this->conn->fetchOne(
            'SELECT state FROM sand_system_plugin WHERE code = ?',
            [self::APP],
        );

        return is_string($state) ? $state : null;
    }

    private function suspendedResidueRows(): int
    {
        $count = $this->conn->fetchOne(
            'SELECT (SELECT COUNT(*) FROM sand_system_schedule WHERE plugin_code = ? AND suspend_time IS NOT NULL)'
            . ' + (SELECT COUNT(*) FROM sand_system_plugin_extension WHERE plugin_code = ? AND suspend_time IS NOT NULL)',
            [self::APP, self::APP],
        );

        return is_numeric($count) ? (int) $count : -1;
    }
}
