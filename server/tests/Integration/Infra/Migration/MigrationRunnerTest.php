<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Migration\Exception\MigrationBusyException;
use Sandadmin\Infra\Migration\Exception\MigrationConsistencyException;
use Sandadmin\Infra\Migration\Exception\MigrationFailedException;
use Sandadmin\Infra\Migration\ManualRecoveryPoint;
use Sandadmin\Infra\Migration\MigrationCatalog;
use Sandadmin\Infra\Migration\MigrationRunner;

/**
 * 迁移执行器真库验证：空库 → 0001…0007 → 回退到 0003 → 再升级到 0007，
 * 外加失败记账/半成品库拒绝与 advisory lock 冲突拒绝。
 * 需要 SAND_TEST_DB_URL 指向临时 PostgreSQL；测试另建 <库名>_migrunner 临时库
 * 跑破坏性场景，不碰共享测试库既有结构（其他集成测试约定基线已应用）。
 *
 * @phpstan-import-type Params from DriverManager
 */
final class MigrationRunnerTest extends TestCase
{
    private const MIGRATIONS = __DIR__ . '/../../../../database/migrations';

    private static ?string $url = null;
    private static ?string $scratchDb = null;
    private static ?Connection $adminConn = null;

    private Connection $conn;

    public static function setUpBeforeClass(): void
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            return;
        }
        $dbname = (new DsnParser())->parse($url)['dbname'] ?? '';
        if ($dbname === '' || preg_match('/^[a-z0-9_]+$/', $dbname) !== 1) {
            self::fail('SAND_TEST_DB_URL 库名非法');
        }
        self::$url = $url;
        self::$scratchDb = $dbname . '_migrunner';
        self::$adminConn = DriverManager::getConnection(self::dbParams('postgres'));
        $exists = self::$adminConn->fetchOne(
            'SELECT COUNT(*) FROM pg_database WHERE datname = :db',
            ['db' => self::$scratchDb],
        );
        if ($exists === 0 || $exists === '0') {
            self::$adminConn->executeStatement(
                'CREATE DATABASE ' . self::$scratchDb
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$adminConn === null || self::$scratchDb === null) {
            return;
        }
        self::$adminConn->executeStatement(
            'DROP DATABASE IF EXISTS ' . self::$scratchDb . ' WITH (FORCE)'
        );
        self::$adminConn->close();
        self::$adminConn = null;
        self::$scratchDb = null;
    }

    protected function setUp(): void
    {
        if (self::$url === null) {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向临时 PostgreSQL');
        }
        $this->conn = DriverManager::getConnection(
            self::dbParams(self::scratchName()),
        );
    }

    protected function tearDown(): void
    {
        $this->conn->close();
    }

    public function testUpgradeDowngradeUpgradeCycle(): void
    {
        $this->resetSchema();
        $runner = $this->runner();

        $report = $runner->upgrade();
        self::assertCount(7, $report['executed']);
        self::assertTrue($this->tableExists('sand_system_schema_migration'));
        self::assertTrue($this->tableExists('sand_system_dict_item_ref'));
        self::assertTrue($this->columnExists('sand_system_metric_sample', 'collected_time'));
        self::assertSame('16', $this->varcharLength('sand_system_announcement', 'level'));
        self::assertTrue($this->columnExists('sand_system_schedule', 'suspend_time'));

        $report = $runner->downgrade('host', '0003');
        self::assertSame(
            ['0007', '0006', '0005', '0004'],
            array_map(static fn (array $item): string => $item['version'], $report['executed']),
        );
        self::assertFalse($this->tableExists('sand_system_dict_item_ref'));
        self::assertTrue($this->columnExists('sand_system_metric_sample', 'sample_time'));
        self::assertTrue($this->columnExists('sand_system_metric_sample', 'mem_used_bytes'));
        self::assertFalse($this->columnExists('sand_system_metric_sample', 'cpu_cores'));
        self::assertFalse($this->columnExists('sand_system_schedule', 'suspend_time'));
        self::assertSame('8', $this->varcharLength('sand_system_announcement', 'level'));

        $report = $runner->upgrade();
        self::assertCount(4, $report['executed']);
        self::assertTrue($this->tableExists('sand_system_dict_item_ref'));
        self::assertTrue($this->columnExists('sand_system_metric_sample', 'collected_time'));

        $status = $runner->status();
        self::assertSame([], $status['pending']);
        self::assertCount(7, $status['rows']);
        foreach ($status['rows'] as $row) {
            self::assertSame('applied', $row->status);
            self::assertSame('up', $row->direction);
        }
    }

    public function testFailedMigrationLeavesFailedRowAndBlocksNextRun(): void
    {
        $this->resetSchema();
        $dir = sys_get_temp_dir() . '/sand_migration_bad_' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        file_put_contents(
            $dir . '/0001_broken_table.sql',
            "-- 迁移版本（release_version）：9.9.9；兼容标记：breaking\n"
            . "CREATE TABLE broken syntax error;\n",
        );
        $runner = new MigrationRunner(
            $this->conn,
            MigrationCatalog::load(['bad' => $dir]),
            new ManualRecoveryPoint('integration-test'),
            'phpunit',
        );

        $this->expectException(MigrationFailedException::class);
        try {
            $runner->upgrade();
        } finally {
            self::assertSame(1, $this->countRows(
                "SELECT COUNT(*) FROM sand_system_schema_migration WHERE status = 'failed'"
            ));
        }

        $this->expectException(MigrationConsistencyException::class);
        $runner->upgrade();
    }

    public function testLockConflictRefusesWithoutQueuing(): void
    {
        $this->resetSchema();
        $runner = $this->runner();

        $holder = DriverManager::getConnection(
            self::dbParams(self::scratchName()),
        );
        $holder->executeStatement('SELECT pg_advisory_lock(:key)', ['key' => $runner->lockKey()]);
        try {
            $this->expectException(MigrationBusyException::class);
            $runner->upgrade();
        } finally {
            $holder->close();
        }
    }

    public function testDowngradeBeyondBaselineIsRefused(): void
    {
        $this->resetSchema();
        $runner = $this->runner();
        $runner->upgrade('0003');

        $this->expectException(\Sandadmin\Infra\Migration\Exception\MigrationException::class);
        $this->expectExceptionMessage('没有回退文件');
        $runner->downgrade('host', '0001');
    }

    private function runner(): MigrationRunner
    {
        return new MigrationRunner(
            $this->conn,
            MigrationCatalog::load(['host' => self::MIGRATIONS]),
            new ManualRecoveryPoint('integration-test'),
            'phpunit',
        );
    }

    /**
     * @return Params
     */
    private static function dbParams(string $dbname): array
    {
        $params = (new DsnParser())->parse(self::$url ?? '');
        $params['dbname'] = $dbname;

        return $params;
    }

    private static function scratchName(): string
    {
        self::assertNotNull(self::$scratchDb);

        return self::$scratchDb;
    }

    private function resetSchema(): void
    {
        $this->conn->executeStatement('DROP SCHEMA public CASCADE');
        $this->conn->executeStatement('CREATE SCHEMA public');
    }

    private function tableExists(string $table): bool
    {
        return is_string($this->conn->fetchOne('SELECT to_regclass(:t)', ['t' => $table]));
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->countRows(
            'SELECT COUNT(*) FROM information_schema.columns'
            . ' WHERE table_schema = :schema AND table_name = :table AND column_name = :column',
            ['schema' => 'public', 'table' => $table, 'column' => $column],
        ) === 1;
    }

    private function varcharLength(string $table, string $column): string
    {
        $length = $this->conn->fetchOne(
            'SELECT character_maximum_length FROM information_schema.columns'
            . ' WHERE table_schema = :schema AND table_name = :table AND column_name = :column',
            ['schema' => 'public', 'table' => $table, 'column' => $column],
        );
        if (!is_int($length) && !is_string($length)) {
            self::fail("{$table}.{$column} 的长度定义读不出来");
        }

        return (string) $length;
    }

    /** @param array<string, mixed> $params */
    private function countRows(string $sql, array $params = []): int
    {
        $value = $this->conn->fetchOne($sql, $params);
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            self::fail('COUNT 查询返回了非整数值');
        }

        return (int) $value;
    }
}
