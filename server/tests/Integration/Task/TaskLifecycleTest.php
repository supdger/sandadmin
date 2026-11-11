<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Integration\Task;

use PHPUnit\Framework\Attributes\Group;
use Sandadmin\Task\InvalidTaskException;
use Sandadmin\Task\TaskState;
use Sandadmin\Tests\Integration\Support\PgTaskCase;

/**
 * 投递 → 认领 → 执行 → 回写 的端到端验证（真实 PostgreSQL）。
 */
#[Group('integration')]
final class TaskLifecycleTest extends PgTaskCase
{
    public function testDispatchThenExecuteReachesSucceeded(): void
    {
        $dispatcher = self::dispatcher();
        $task = $dispatcher->dispatch('noop', ['source' => 'test'], self::$userId, 'life-key-1');
        self::assertSame(TaskState::Queued, $task->state);

        // 同键重复投递返回既有记录，不产生第二条
        $again = $dispatcher->dispatch('noop', ['source' => 'changed'], self::$userId, 'life-key-1');
        self::assertSame($task->publicId, $again->publicId);

        $done = self::executor()->tick();
        self::assertNotNull($done);
        self::assertSame($task->publicId, $done->publicId);
        self::assertSame(TaskState::Succeeded, $done->state);
        self::assertSame(['ok' => true], $done->result);

        // 队列已空
        self::assertNull(self::executor()->tick());
    }

    public function testSecretPayloadRejectedBeforeInsert(): void
    {
        $this->expectException(InvalidTaskException::class);
        self::dispatcher()->dispatch('noop', ['password' => 'should-not-store'], self::$userId);
    }

    public function testUnknownTypeFailsAtExecution(): void
    {
        $task = self::dispatcher()->dispatch('unregistered.type', [], self::$userId);
        $done = self::executor()->tick();
        self::assertNotNull($done);
        self::assertSame(TaskState::Failed, $done->state);
        self::assertSame('unknown_type', $done->errorCode);
        self::assertNotNull($done->finishTime);
    }
}
