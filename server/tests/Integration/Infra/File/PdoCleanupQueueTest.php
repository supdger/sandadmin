<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\File;

use PHPUnit\Framework\Attributes\Group;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Infra\File\PdoCleanupQueue;
use Sandadmin\Tests\Integration\Support\PgFileCase;

/**
 * PdoCleanupQueue 对 sand_system_task_run 的投递与幂等验证。
 * 需要 SAND_TEST_DB_URL。
 */
#[Group('integration')]
final class PdoCleanupQueueTest extends PgFileCase
{
    public function testEnqueueCreatesQueuedTaskRun(): void
    {
        $queue = new PdoCleanupQueue(self::pdo());
        $accepted = $queue->enqueue(self::$userId, null);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $accepted['task_id']
        );
        self::assertSame('queued', $accepted['state']);

        $statement = self::pdo()
            ->query("SELECT type, state, stage, progress, created_by, payload::text AS payload"
                . " FROM sand_system_task_run WHERE public_id = CAST('" . $accepted['task_id'] . "' AS uuid)");
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $row = $statement->fetch();
        self::assertIsArray($row);
        self::assertSame('cleanup.files', $row['type']);
        self::assertSame('queued', $row['state']);
        self::assertSame('排队中', $row['stage']);
        $progress = $row['progress'] ?? null;
        $createdBy = $row['created_by'] ?? null;
        self::assertTrue(is_numeric($progress));
        self::assertSame(0, (int) $progress);
        self::assertTrue(is_numeric($createdBy));
        self::assertSame(self::$userId, (int) $createdBy);
        self::assertSame('{}', $row['payload']);
    }

    public function testEnqueueIdempotentReplay(): void
    {
        $queue = new PdoCleanupQueue(self::pdo());
        $first = $queue->enqueue(self::$userId, 'sweep-1');
        $second = $queue->enqueue(self::$userId, 'sweep-1');

        self::assertSame($first['task_id'], $second['task_id']);
        $statement = self::pdo()
            ->query("SELECT COUNT(*) FROM sand_system_task_run WHERE type = 'cleanup.files' AND idempotency_key = 'sweep-1'");
        self::assertInstanceOf(\PDOStatement::class, $statement);
        $count = $statement->fetchColumn();
        self::assertTrue(is_numeric($count));
        self::assertSame(1, (int) $count);
    }

    public function testDifferentKeysCreateSeparateTasks(): void
    {
        $queue = new PdoCleanupQueue(self::pdo());
        $a = $queue->enqueue(self::$userId, 'k-a');
        $b = $queue->enqueue(self::$otherUserId, 'k-b');
        self::assertNotSame($a['task_id'], $b['task_id']);
    }

    public function testRejectsInvalidCreatedBy(): void
    {
        $queue = new PdoCleanupQueue(self::pdo());
        $this->expectException(FileException::class);
        $queue->enqueue(0, null);
    }
}
