<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Sandadmin\Infra\Migration\Exception\MigrationConsistencyException;
use Sandadmin\Infra\Migration\Exception\MigrationException;
use Sandadmin\Infra\Migration\Exception\MigrationFailedException;

/**
 * 统一迁移执行器：宿主与插件共用，账本按命名连接各记一本（M-05）。
 * 执行纪律：单进程 advisory lock、每份迁移单独一个事务、checksum 防改、
 * 半成品库拒绝、批次前必须拿到恢复点。本批只支持 PostgreSQL。
 */
final class MigrationRunner
{
    private readonly MigrationLedger $ledger;
    private readonly MigrationLock $lock;

    public function __construct(
        private readonly Connection $conn,
        private readonly MigrationCatalog $catalog,
        private readonly RecoveryPointProvider $recoveryPoint,
        private readonly string $actor,
    ) {
        $this->ledger = new MigrationLedger($conn);
        $this->lock = new MigrationLock($conn);
    }

    public function lockKey(): int
    {
        return $this->lock->key();
    }

    /**
     * 升级：按命名空间、版本顺序执行所有未应用的迁移。
     *
     * @param string|null $to 只执行到该版本（含）；null = 全部
     * @return array{recovery_point: string, executed: list<array{namespace: string, version: string, execution_ms: int}>}
     */
    public function upgrade(?string $to = null): array
    {
        $this->assertPostgres();
        $this->lock->acquire();
        try {
            $files = $this->catalog->all();
            $rows = $this->ledger->rows();
            $this->assertConsistent($files, $rows);

            $applied = $this->appliedKeys($rows);
            $pending = [];
            foreach ($files as $file) {
                $key = $file->namespace . '' . $file->version;
                if (isset($applied[$key])) {
                    continue;
                }
                if ($to !== null && $file->version > $to) {
                    continue;
                }
                $pending[] = $file;
            }
            if ($pending === []) {
                return ['recovery_point' => '', 'executed' => []];
            }
            $reference = $this->recoveryPoint->describe();

            $done = [];
            foreach ($pending as $file) {
                $done[] = [
                    'namespace' => $file->namespace,
                    'version' => $file->version,
                    'execution_ms' => $this->apply($file),
                ];
            }

            return ['recovery_point' => $reference, 'executed' => $done];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * 回退：把指定命名空间回退到版本 $to（含），倒序执行各版本的 .down.sql。
     *
     * @return array{recovery_point: string, executed: list<array{namespace: string, version: string, execution_ms: int}>}
     */
    public function downgrade(string $namespace, string $to): array
    {
        $this->assertPostgres();
        $this->lock->acquire();
        try {
            $files = $this->catalog->forNamespace($namespace);
            if ($files === []) {
                throw new MigrationException("命名空间 {$namespace} 没有迁移文件");
            }
            $rows = $this->ledger->rows();
            $this->assertConsistent($this->catalog->all(), $rows);

            $fileByVersion = [];
            foreach ($files as $file) {
                $fileByVersion[$file->version] = $file;
            }
            if (!isset($fileByVersion[$to])) {
                throw new MigrationException("目标版本 {$to} 在命名空间 {$namespace} 里不存在");
            }

            $appliedVersions = [];
            foreach ($rows as $row) {
                if ($row->namespace === $namespace && $row->status === 'applied') {
                    $appliedVersions[] = $row->version;
                }
            }
            if (!in_array($to, $appliedVersions, true)) {
                throw new MigrationException(
                    "目标版本 {$to} 未处于 applied 状态，无法作为回退边界"
                );
            }
            $plan = array_values(array_filter(
                $appliedVersions,
                static fn (string $version): bool => $version > $to,
            ));
            rsort($plan, SORT_STRING);
            foreach ($plan as $version) {
                if (!$fileByVersion[$version]->hasDown()) {
                    throw new MigrationException(
                        "迁移 {$namespace}/{$version} 没有回退文件，无法回退到 {$to}"
                        . '（基线迁移不带回退脚本）'
                    );
                }
            }
            if ($plan === []) {
                return ['recovery_point' => '', 'executed' => []];
            }
            $reference = $this->recoveryPoint->describe();

            $done = [];
            foreach ($plan as $version) {
                $done[] = [
                    'namespace' => $namespace,
                    'version' => $version,
                    'execution_ms' => $this->revert($fileByVersion[$version]),
                ];
            }

            return ['recovery_point' => $reference, 'executed' => $done];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * 账本与待执行清单（status 子命令用）。账本不存在时视为全待执行。
     *
     * @return array{rows: list<LedgerRow>, pending: list<MigrationFile>}
     */
    public function status(): array
    {
        $rows = $this->ledger->exists() ? $this->ledger->rows() : [];
        $applied = $this->appliedKeys($rows);
        $pending = array_values(array_filter(
            $this->catalog->all(),
            static fn (MigrationFile $file): bool
                => !isset($applied[$file->namespace . '' . $file->version]),
        ));

        return ['rows' => $rows, 'pending' => $pending];
    }

    /**
     * 单份升级迁移：文件内容 + 账本记账放在同一个事务里，失败整体回滚。
     *
     * @return int 执行耗时（毫秒）
     */
    private function apply(MigrationFile $file): int
    {
        $start = microtime(true);
        try {
            $this->conn->beginTransaction();
            $this->execFile($file->path);
            $ms = $this->elapsedMs($start);
            $this->ledger->ensureTable();
            $this->ledger->record(
                $file, 'up', 'applied', $file->name, $file->checksum, $this->actor, $ms
            );
            $this->conn->commit();

            return $ms;
        } catch (\Throwable $error) {
            if ($this->conn->isTransactionActive()) {
                $this->conn->rollBack();
            }
            $this->ledger->recordFailed(
                $file, 'up', $file->name, $file->checksum, $this->actor, $this->elapsedMs($start)
            );
            throw new MigrationFailedException(
                "迁移 {$file->name} 执行失败，已整体回滚并记 failed：{$error->getMessage()}",
                0,
                $error,
            );
        }
    }

    /**
     * 单份回退：同 apply，事务内执行 .down.sql 并记账 rolled_back。
     *
     * @return int 执行耗时（毫秒）
     */
    private function revert(MigrationFile $file): int
    {
        $start = microtime(true);
        $downName = $file->downName ?? '';
        try {
            $this->conn->beginTransaction();
            $this->execFile((string) $file->downPath);
            $ms = $this->elapsedMs($start);
            $this->ledger->ensureTable();
            $this->ledger->record(
                $file, 'down', 'rolled_back', $downName, $file->downChecksum(), $this->actor, $ms
            );
            $this->conn->commit();

            return $ms;
        } catch (\Throwable $error) {
            if ($this->conn->isTransactionActive()) {
                $this->conn->rollBack();
            }
            $this->ledger->recordFailed(
                $file, 'down', $downName, $file->downChecksum(), $this->actor, $this->elapsedMs($start)
            );
            throw new MigrationFailedException(
                "回退 {$downName} 执行失败，已整体回滚并记 failed：{$error->getMessage()}",
                0,
                $error,
            );
        }
    }

    /**
     * 迁移文件是多语句 SQL，必须走原生连接的 simple query 协议；
     * 预处理协议一次只接受一条语句。pdo_pgsql 用 PDO::exec，ext-pgsql 用 pg_query。
     */
    private function execFile(string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new MigrationException("迁移文件不可读：{$path}");
        }
        $native = $this->conn->getNativeConnection();
        if ($native instanceof \PDO) {
            $native->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $native->exec($sql);

            return;
        }
        if ($native instanceof \PgSql\Connection) {
            // pg_query 失败既返回 false 又发 warning，错误信息统一走 pg_last_error
            if (@pg_query($native, $sql) === false) {
                throw new MigrationException(
                    '迁移 SQL 执行失败：' . (pg_last_error($native) ?: '未知错误')
                );
            }

            return;
        }
        throw new MigrationException('迁移执行器要求 pdo_pgsql 或 pgsql 连接');
    }

    /**
     * @param list<MigrationFile> $files
     * @param list<LedgerRow> $rows
     */
    private function assertConsistent(array $files, array $rows): void
    {
        $problems = LedgerConsistency::problems($files, $rows);
        if ($problems !== []) {
            throw new MigrationConsistencyException(LedgerConsistency::guidance($problems));
        }
    }

    /**
     * @param list<LedgerRow> $rows
     * @return array<string, true>
     */
    private function appliedKeys(array $rows): array
    {
        $applied = [];
        foreach ($rows as $row) {
            if ($row->status === 'applied') {
                $applied[$row->namespace . '' . $row->version] = true;
            }
        }

        return $applied;
    }

    private function assertPostgres(): void
    {
        if (!$this->conn->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            throw new MigrationException('本批次迁移执行器只支持 PostgreSQL');
        }
    }

    private function releaseLock(): void
    {
        try {
            $this->lock->release();
        } catch (\Throwable) {
            // 释放失败不掩盖正在抛出的原始错误；连接关闭时锁自然失效
        }
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
