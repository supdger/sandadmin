<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\Migration\Exception\MigrationException;

/**
 * 账本 sand_system_schema_migration：每条命名连接一本，命名空间区分宿主与插件归属。
 * (namespace, version) 唯一；一行记录该版本最近一次操作的方向与结果。
 * 基线 0001 内嵌了草稿期旧列结构的同名表（version 主键、无 namespace/status），
 * ensureTable 在同一事务里把它升级到定稿列结构——基线文件按 C7 逐字节锁定，不改文件。
 */
final class MigrationLedger
{
    private const TABLE = 'sand_system_schema_migration';

    public function __construct(private readonly Connection $conn)
    {
    }

    /**
     * 保证账本为定稿列结构：不存在则创建；旧形（0001 内嵌）就地升级；形状无法识别则报错。
     * 必须在执行器事务内调用（0001 会先建出旧形表，再被本方法升级）。
     */
    public function ensureTable(): void
    {
        if (!$this->exists()) {
            $this->createTable();
            return;
        }
        if (!$this->hasColumn('namespace')) {
            $this->upgradeLegacyShape();
        }
        $this->assertShape();
    }

    public function exists(): bool
    {
        $found = $this->conn->executeQuery(
            'SELECT to_regclass(:name)',
            ['name' => self::TABLE],
        )->fetchOne();

        return is_string($found) && $found !== '';
    }

    /** @return list<LedgerRow> 账本不存在时返回空 */
    public function rows(): array
    {
        if (!$this->exists()) {
            return [];
        }
        $rows = $this->conn->fetchAllAssociative(
            'SELECT namespace, version, filename, checksum, direction, status,'
            . ' release_version, applied_by, applied_time, execution_ms'
            . ' FROM ' . self::TABLE
            . ' ORDER BY namespace ASC, version ASC'
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = new LedgerRow(
                self::str($row['namespace']),
                self::str($row['version']),
                self::str($row['filename']),
                self::str($row['checksum']),
                self::str($row['direction']),
                self::str($row['status']),
                is_string($row['release_version']) ? $row['release_version'] : null,
                self::str($row['applied_by']),
                self::str($row['applied_time']),
                self::toInt($row['execution_ms']),
            );
        }

        return $result;
    }

    /**
     * 记一行账本：同一 (namespace, version) 只有一行，记最近一次操作。
     * 与迁移文件在同一事务里调用，记账和结构变更同生死；
     * 唯一的例外是 recordFailed——它在失败事务回滚后独立提交。
     *
     * @param 'up'|'down' $direction
     * @param 'applied'|'failed'|'rolled_back' $status
     */
    public function record(
        MigrationFile $file,
        string $direction,
        string $status,
        string $filename,
        string $checksum,
        string $appliedBy,
        int $executionMs,
    ): void {
        $this->conn->executeStatement(<<<'SQL'
            INSERT INTO sand_system_schema_migration
                (namespace, version, filename, checksum, direction, status,
                 release_version, applied_by, applied_time, execution_ms)
            VALUES
                (:namespace, :version, :filename, :checksum, :direction, :status,
                 :release_version, :applied_by, CURRENT_TIMESTAMP, :execution_ms)
            ON CONFLICT (namespace, version) DO UPDATE SET
                filename        = EXCLUDED.filename,
                checksum        = EXCLUDED.checksum,
                direction       = EXCLUDED.direction,
                status          = EXCLUDED.status,
                release_version = EXCLUDED.release_version,
                applied_by      = EXCLUDED.applied_by,
                applied_time    = EXCLUDED.applied_time,
                execution_ms    = EXCLUDED.execution_ms
            SQL, [
            'namespace' => $file->namespace,
            'version' => $file->version,
            'filename' => $filename,
            'checksum' => $checksum,
            'direction' => $direction,
            'status' => $status,
            'release_version' => $file->releaseVersion,
            'applied_by' => $appliedBy,
            'execution_ms' => $executionMs,
        ]);
    }

    /**
     * 迁移失败后回写 failed：失败事务已回滚，本条独立提交，把半成品库状态钉死在账本上。
     * 记账本身也失败（如连接已断）时抛出 MigrationException，不再掩盖原始错误。
     *
     * @param 'up'|'down' $direction
     */
    public function recordFailed(
        MigrationFile $file,
        string $direction,
        string $filename,
        string $checksum,
        string $appliedBy,
        int $executionMs,
    ): void {
        try {
            $this->ensureTable();
            $this->record($file, $direction, 'failed', $filename, $checksum, $appliedBy, $executionMs);
        } catch (\Throwable $error) {
            throw new MigrationException(
                "迁移 {$filename} 失败，且账本 failed 记账也失败：{$error->getMessage()}",
                0,
                $error,
            );
        }
    }

    private function createTable(): void
    {
        $this->conn->executeStatement(<<<'SQL'
            CREATE TABLE sand_system_schema_migration (
                id              integer GENERATED BY DEFAULT AS IDENTITY,
                namespace       varchar(64)  NOT NULL,
                version         varchar(8)   NOT NULL,
                filename        varchar(255) NOT NULL,
                checksum        char(64)     NOT NULL,
                direction       varchar(8)   NOT NULL,
                status          varchar(16)  NOT NULL,
                release_version varchar(32)  NULL,
                applied_by      varchar(64)  NOT NULL,
                applied_time    timestamptz(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
                execution_ms    integer      NOT NULL DEFAULT 0,
                CONSTRAINT pk_sand_system_schema_migration PRIMARY KEY (id),
                CONSTRAINT uk_sand_system_schema_migration_version
                    UNIQUE (namespace, version),
                CONSTRAINT ck_sand_system_schema_migration_direction
                    CHECK (direction IN ('up', 'down')),
                CONSTRAINT ck_sand_system_schema_migration_status
                    CHECK (status IN ('applied', 'failed', 'rolled_back'))
            )
            SQL);
        $this->conn->executeStatement(
            "COMMENT ON TABLE sand_system_schema_migration IS '结构迁移账本：每条命名连接一本，命名空间区分宿主与插件归属'"
        );
    }

    /**
     * 0001 内嵌的旧形升级为定稿列结构，分两条语句：同一条 ALTER 的子命令并行应用，
     * 引用新列的约束不能和 ADD COLUMN 写在一起。旧形表在执行器语境下必为空，
     * 非空则如实报错，不静默补值。
     */
    private function upgradeLegacyShape(): void
    {
        $this->conn->executeStatement(<<<'SQL'
            ALTER TABLE sand_system_schema_migration
                DROP CONSTRAINT sand_system_schema_migration_pkey,
                ADD COLUMN id integer GENERATED BY DEFAULT AS IDENTITY,
                ADD COLUMN namespace varchar(64) NOT NULL,
                ADD COLUMN filename varchar(255) NOT NULL,
                ADD COLUMN direction varchar(8) NOT NULL,
                ADD COLUMN status varchar(16) NOT NULL,
                ADD COLUMN applied_by varchar(64) NOT NULL,
                ALTER COLUMN version TYPE varchar(8)
            SQL);
        $this->conn->executeStatement(<<<'SQL'
            ALTER TABLE sand_system_schema_migration
                ADD CONSTRAINT pk_sand_system_schema_migration PRIMARY KEY (id),
                ADD CONSTRAINT uk_sand_system_schema_migration_version
                    UNIQUE (namespace, version),
                ADD CONSTRAINT ck_sand_system_schema_migration_direction
                    CHECK (direction IN ('up', 'down')),
                ADD CONSTRAINT ck_sand_system_schema_migration_status
                    CHECK (status IN ('applied', 'failed', 'rolled_back'))
            SQL);
    }

    private function hasColumn(string $column): bool
    {
        $count = $this->conn->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns'
            . ' WHERE table_schema = current_schema() AND table_name = :table AND column_name = :column',
            ['table' => self::TABLE, 'column' => $column],
        );

        return $count === 1 || $count === '1';
    }

    private function assertShape(): void
    {
        foreach (['namespace', 'version', 'checksum', 'direction', 'status', 'applied_time'] as $column) {
            if (!$this->hasColumn($column)) {
                throw new MigrationException(
                    self::TABLE . " 缺少 {$column} 列：账本结构无法识别，请人工核对后修正"
                );
            }
        }
    }

    private static function str(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new MigrationException('账本读出非字符串列值');
        }

        return (string) $value;
    }

    private static function toInt(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new MigrationException('账本 execution_ms 读出非整数列值');
        }

        return (int) $value;
    }
}
