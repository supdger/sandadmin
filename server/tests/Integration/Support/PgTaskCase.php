<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Integration\Support;

use PHPUnit\Framework\TestCase;
use Sandadmin\Task\MappedTaskHandlerFactory;
use Sandadmin\Task\PdoTaskStore;
use Sandadmin\Task\SystemClock;
use Sandadmin\Task\TaskConfig;
use Sandadmin\Task\TaskDispatcher;
use Sandadmin\Task\TaskExecutor;
use Sandadmin\Task\TaskHandlerCatalog;

/**
 * 需要真实 PostgreSQL 的任务集成测试基类。
 * 连接串取环境变量 SAND_TEST_DB_URL（pgsql://user[:pass]@host:port/db），
 * 未设置或连不上时全部跳过；表结构须已应用 0001 基线迁移，
 * 测试自身不建表。
 */
abstract class PgTaskCase extends TestCase
{
    protected static ?\PDO $pdo = null;

    protected static bool $available = false;

    protected static int $deptId = 0;

    protected static int $userId = 0;

    protected static int $otherUserId = 0;

    public static function setUpBeforeClass(): void
    {
        $pdo = self::connect();
        if ($pdo === null) {
            self::$available = false;

            return;
        }
        try {
            $pdo->query('SELECT 1 FROM sand_system_task_run LIMIT 1');
        } catch (\PDOException) {
            self::$available = false;

            return;
        }
        self::$pdo = $pdo;
        self::$available = true;
        self::ensureFixtureUsers($pdo);
    }

    protected function setUp(): void
    {
        if (!self::$available) {
            self::markTestSkipped('需要 SAND_TEST_DB_URL 指向已应用基线迁移的 PostgreSQL');
        }
        self::pdo()->exec('DELETE FROM sand_system_task_run');
    }

    protected static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            throw new \LogicException('测试数据库未连接');
        }

        return self::$pdo;
    }

    protected static function store(): PdoTaskStore
    {
        return new PdoTaskStore(self::pdo());
    }

    protected static function dispatcher(): TaskDispatcher
    {
        return new TaskDispatcher(self::store(), new SystemClock());
    }

    /**
     * @param array<string, mixed> $config
     */
    protected static function executor(array $config = []): TaskExecutor
    {
        $merged = $config + [
            'lease_seconds' => 60,
            'max_attempts' => 3,
            'timeout_seconds' => 300,
            'default_concurrency' => 4,
            'poll_interval' => 1.0,
            'max_batch' => 10,
        ];
        $clock = new SystemClock();
        $store = self::store();

        return new TaskExecutor(
            $store,
            new MappedTaskHandlerFactory(TaskHandlerCatalog::host()),
            $clock,
            'pg-test-worker',
            TaskConfig::fromArray($merged),
        );
    }

    /**
     * created_by 有外键，测试用户每个类建一次、用完保留（幂等键按手机号复用）。
     */
    private static function ensureFixtureUsers(\PDO $pdo): void
    {
        $findUser = $pdo->prepare('SELECT id FROM sand_system_user WHERE phone = :phone');
        $insertUser = $pdo->prepare(
            'INSERT INTO sand_system_user (phone, nickname, password_hash, dept_id, status)
             VALUES (:phone, :nickname, :hash, :dept, 1) RETURNING id'
        );
        $dept = $pdo->query("SELECT id FROM sand_system_dept WHERE name = '任务测试部' LIMIT 1");
        $deptId = $dept === false ? false : $dept->fetchColumn();
        if ($deptId === false) {
            $stmt = $pdo->prepare("INSERT INTO sand_system_dept (name, status) VALUES ('任务测试部', 1) RETURNING id");
            $stmt->execute();
            $deptId = (int) $stmt->fetchColumn();
        }
        self::$deptId = (int) $deptId;

        foreach ([self::class, 'other'] as $label) {
            $phone = $label === self::class ? '13900000001' : '13900000002';
            $findUser->execute(['phone' => $phone]);
            $id = $findUser->fetchColumn();
            if ($id === false) {
                $insertUser->execute([
                    'phone' => $phone,
                    'nickname' => '任务测试用户',
                    'hash' => 'test-hash',
                    'dept' => self::$deptId,
                ]);
                $id = $insertUser->fetchColumn();
            }
            if ($label === self::class) {
                self::$userId = (int) $id;
            } else {
                self::$otherUserId = (int) $id;
            }
        }
    }

    private static function connect(): ?\PDO
    {
        $url = getenv('SAND_TEST_DB_URL');
        if (!is_string($url) || $url === '') {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'pgsql') {
            return null;
        }
        $host = (string) ($parts['host'] ?? '127.0.0.1');
        $port = (int) ($parts['port'] ?? 5432);
        $database = ltrim((string) ($parts['path'] ?? ''), '/');
        $username = (string) ($parts['user'] ?? 'postgres');
        $password = (string) ($parts['pass'] ?? '');
        if ($database === '') {
            return null;
        }
        try {
            return new \PDO(
                sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database),
                $username,
                $password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        } catch (\PDOException) {
            return null;
        }
    }
}
