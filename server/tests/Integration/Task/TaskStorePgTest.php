<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Integration\Task;

use PHPUnit\Framework\Attributes\Group;
use Sandadmin\Task\TaskClaimPolicy;
use Sandadmin\Task\TaskIdempotencyConflictException;
use Sandadmin\Task\TaskState;
use Sandadmin\Task\TaskStorageException;
use Sandadmin\Tests\Integration\Support\PgTaskCase;
use Sandadmin\Tests\Support\TaskRunFixture;

/**
 * PdoTaskStore 对 sand_system_task_run 基线表的读写与认领语义验证。
 * 需要 SAND_TEST_DB_URL 指向已应用 0001 基线迁移的 PostgreSQL。
 */
#[Group('integration')]
final class TaskStorePgTest extends PgTaskCase
{
    public function testInsertAndFindRoundtrip(): void
    {
        $store = self::store();
        $task = TaskRunFixture::make(
            type: 'export.users',
            createdBy: self::$userId,
            payload: ['scope' => 'all', 'page' => 1],
        );
        $store->insert($task);

        $found = $store->findByPublicId($task->publicId);
        self::assertNotNull($found);
        self::assertSame('export.users', $found->type);
        self::assertSame(TaskState::Queued, $found->state);
        // jsonb 不保留键序，逐键断言而不是整体比对
        self::assertSame('all', $found->payload['scope'] ?? null);
        self::assertSame(1, $found->payload['page'] ?? null);
        self::assertSame(self::$userId, $found->createdBy);
    }

    public function testPublicIdIsUuidAndUnique(): void
    {
        $store = self::store();
        $task = TaskRunFixture::make(createdBy: self::$userId);
        $store->insert($task);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $task->publicId
        );
        $this->expectException(TaskStorageException::class);
        $store->insert($task);
    }

    public function testIdempotencyKeyUniquePerType(): void
    {
        $store = self::store();
        $first = TaskRunFixture::make(type: 'noop', createdBy: self::$userId, idempotencyKey: 'dup-key');
        $store->insert($first);
        $second = TaskRunFixture::make(type: 'noop', createdBy: self::$userId, idempotencyKey: 'dup-key');
        try {
            $store->insert($second);
            self::fail('应抛出幂等冲突');
        } catch (TaskIdempotencyConflictException) {
        }
        // 不同类型同键允许
        $other = TaskRunFixture::make(type: 'other', createdBy: self::$userId, idempotencyKey: 'dup-key');
        $store->insert($other);
        self::assertNotNull($store->findByIdempotencyKey('noop', 'dup-key'));
        self::assertNotNull($store->findByIdempotencyKey('other', 'dup-key'));
    }

    public function testClaimTransitionsToRunningWithLease(): void
    {
        $store = self::store();
        $task = TaskRunFixture::make(createdBy: self::$userId);
        $store->insert($task);
        $now = new \DateTimeImmutable('2026-11-10 12:00:00', new \DateTimeZone('UTC'));
        $policy = new TaskClaimPolicy(60, 3, [], 4);

        $claimed = $store->claimNext('worker-1', $now, $policy);
        self::assertNotNull($claimed);
        self::assertSame(TaskState::Running, $claimed->state);
        self::assertSame('worker-1', $claimed->lockedBy);
        self::assertSame(1, $claimed->attempt);
        self::assertNotNull($claimed->leaseExpireTime);
        self::assertNotNull($claimed->startTime);

        // 租约未过期：第二个 worker 领不到
        $again = $store->claimNext('worker-2', $now->modify('+30 seconds'), $policy);
        self::assertNull($again);
    }

    public function testExpiredLeaseIsReclaimed(): void
    {
        $store = self::store();
        $task = TaskRunFixture::make(createdBy: self::$userId);
        $store->insert($task);
        $now = new \DateTimeImmutable('2026-11-10 12:00:00', new \DateTimeZone('UTC'));
        $policy = new TaskClaimPolicy(60, 3, [], 4);
        $first = $store->claimNext('worker-dead', $now, $policy);
        self::assertNotNull($first);

        $reclaimed = $store->claimNext('worker-live', $now->modify('+61 seconds'), $policy);
        self::assertNotNull($reclaimed);
        self::assertSame($task->publicId, $reclaimed->publicId);
        self::assertSame(2, $reclaimed->attempt);
        self::assertSame('worker-live', $reclaimed->lockedBy);
    }

    public function testMaxAttemptsMarksFailed(): void
    {
        $store = self::store();
        $task = TaskRunFixture::make(createdBy: self::$userId);
        $store->insert($task);
        $now = new \DateTimeImmutable('2026-11-10 12:00:00', new \DateTimeZone('UTC'));
        $policy = new TaskClaimPolicy(60, 1, [], 4);
        $claimed = $store->claimNext('w1', $now, $policy);
        self::assertNotNull($claimed);
        self::assertSame(1, $claimed->attempt);

        $next = $store->claimNext('w2', $now->modify('+61 seconds'), $policy);
        self::assertNull($next);
        $after = $store->findByPublicId($task->publicId);
        self::assertSame(TaskState::Failed, $after?->state);
        self::assertSame('max_attempts_exceeded', $after->errorCode);
        self::assertNotNull($after->finishTime);
    }

    public function testConcurrencyLimitPerType(): void
    {
        $store = self::store();
        $store->insert(TaskRunFixture::make(type: 'limited', createdBy: self::$userId));
        $store->insert(TaskRunFixture::make(type: 'limited', createdBy: self::$userId));
        $store->insert(TaskRunFixture::make(type: 'free', createdBy: self::$userId));
        $now = new \DateTimeImmutable('2026-11-10 12:00:00', new \DateTimeZone('UTC'));
        $policy = new TaskClaimPolicy(60, 3, ['limited' => 1], 4);

        $first = $store->claimNext('w1', $now, $policy);
        self::assertNotNull($first);
        // limited 已达上限 1，但 free 类型仍可领
        $second = $store->claimNext('w2', $now, $policy);
        self::assertNotNull($second);
        self::assertSame('free', $second->type);
        $third = $store->claimNext('w3', $now, $policy);
        self::assertNull($third);
    }

    public function testProgressHeartbeatAndTerminalGuards(): void
    {
        $store = self::store();
        $task = TaskRunFixture::make(createdBy: self::$userId);
        $store->insert($task);
        $now = new \DateTimeImmutable('2026-11-10 12:00:00', new \DateTimeZone('UTC'));
        $policy = new TaskClaimPolicy(60, 3, [], 4);
        $claimed = $store->claimNext('w1', $now, $policy);
        self::assertNotNull($claimed);

        // 其他 worker 不能回写
        try {
            $store->reportProgress($task->publicId, 'w2', 'x', 10, $now, 60);
            self::fail('应拒绝非持有者回写');
        } catch (TaskStorageException) {
        }

        $store->reportProgress($task->publicId, 'w1', '导出中', 60, $now->modify('+10 seconds'), 60);
        $running = $store->findByPublicId($task->publicId);
        self::assertSame(60, $running?->progress);
        self::assertSame('导出中', $running->stage);

        $store->markSucceeded($task->publicId, 'w1', ['file_id' => 3], $now->modify('+20 seconds'));
        $done = $store->findByPublicId($task->publicId);
        self::assertSame(TaskState::Succeeded, $done?->state);
        self::assertSame(100, $done->progress);
        self::assertSame(['file_id' => 3], $done->result);
        self::assertNull($done->leaseExpireTime);
        self::assertNotNull($done->finishTime);

        // 终态后无法再被认领或回写
        self::assertNull($store->claimNext('w9', $now->modify('+3600 seconds'), $policy));
        $this->expectException(TaskStorageException::class);
        $store->markFailed($task->publicId, 'w1', 'x', 'y', $now);
    }

    public function testSearchFiltersAndPaginates(): void
    {
        $store = self::store();
        for ($i = 0; $i < 5; $i++) {
            $store->insert(TaskRunFixture::make(
                type: $i % 2 === 0 ? 'alpha' : 'beta',
                createdBy: $i < 3 ? self::$userId : self::$otherUserId,
            ));
        }
        $own = $store->search(self::$userId, null, null, 1, 20);
        self::assertSame(3, $own->total);
        $byType = $store->search(null, 'alpha', null, 1, 20);
        self::assertSame(3, $byType->total);
        $page = $store->search(null, null, null, 2, 2);
        self::assertSame(5, $page->total);
        self::assertCount(2, $page->items);
        self::assertSame(2, $page->page);
    }
}
