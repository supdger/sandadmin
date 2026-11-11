<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Sandadmin\Task\InvalidTaskException;
use Sandadmin\Task\MappedTaskHandlerFactory;
use Sandadmin\Task\TaskConfig;
use Sandadmin\Task\TaskDispatcher;
use Sandadmin\Task\TaskExecutor;
use Sandadmin\Task\TaskHandler;
use Sandadmin\Task\TaskProgress;
use Sandadmin\Task\TaskRun;
use Sandadmin\Task\TaskState;
use Sandadmin\Task\UnknownTaskTypeException;
use Sandadmin\Tests\Support\FakeTaskStore;
use Sandadmin\Tests\Support\FixedClock;

final class TaskExecutorTest extends TestCase
{
    private FakeTaskStore $store;
    private FixedClock $clock;
    private TaskDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->store = new FakeTaskStore();
        $this->clock = new FixedClock();
        $this->dispatcher = new TaskDispatcher($this->store, $this->clock);
    }

    /**
     * @param array<string, TaskHandler> $handlers
     * @param array<string, mixed> $config
     */
    private function executor(array $handlers = [], array $config = []): TaskExecutor
    {
        $merged = $config + [
            'lease_seconds' => 60,
            'max_attempts' => 3,
            'timeout_seconds' => 300,
            'default_concurrency' => 4,
        ];

        return new TaskExecutor(
            $this->store,
            new MappedTaskHandlerFactory($handlers + ['noop' => new \Sandadmin\Task\Handler\NoopTaskHandler()]),
            $this->clock,
            'worker-test',
            TaskConfig::fromArray($merged),
        );
    }

    public function testTickReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->executor()->tick());
    }

    public function testTickRunsTaskToSucceeded(): void
    {
        $dispatched = $this->dispatcher->dispatch('noop', [], 7);
        $done = $this->executor()->tick();
        self::assertNotNull($done);
        self::assertSame(TaskState::Succeeded, $done->state);
        self::assertSame(100, $done->progress);
        self::assertSame('已完成', $done->stage);
        self::assertSame(['ok' => true], $done->result);
        self::assertNotNull($done->startTime);
        self::assertNotNull($done->finishTime);
        self::assertSame(1, $done->attempt);
        self::assertSame('worker-test', $done->lockedBy);
        self::assertNull($this->executor()->tick());
        self::assertSame($dispatched->publicId, $done->publicId);
    }

    public function testTickFailsUnknownType(): void
    {
        $this->dispatcher->dispatch('missing.handler', [], 7);
        $done = $this->executor()->tick();
        self::assertNotNull($done);
        self::assertSame(TaskState::Failed, $done->state);
        self::assertSame('unknown_type', $done->errorCode);
    }

    public function testTickFailsThrowingHandler(): void
    {
        $this->dispatcher->dispatch('boom', [], 7);
        $done = $this->executor([
            'boom' => new class implements TaskHandler {
                public function handle(TaskRun $task, TaskProgress $progress): ?array
                {
                    throw new \RuntimeException('boom');
                }
            },
        ])->tick();
        self::assertNotNull($done);
        self::assertSame(TaskState::Failed, $done->state);
        self::assertSame('handler_failed', $done->errorCode);
    }

    public function testTickFailsOnTimeoutDuringProgress(): void
    {
        $this->dispatcher->dispatch('slow', [], 7);
        $handler = new class($this->clock) implements TaskHandler {
            public function __construct(private FixedClock $clock)
            {
            }

            public function handle(TaskRun $task, TaskProgress $progress): ?array
            {
                // 心跳前先把时钟推过超时上限：deadline 判定应在 report 内触发
                $this->clock->advance(400);
                $progress->report('仍在跑', 90);

                return null;
            }
        };
        $done = $this->executor(['slow' => $handler], ['timeout_seconds' => 300])->tick();
        self::assertNotNull($done);
        self::assertSame(TaskState::Failed, $done->state);
        self::assertSame('timeout', $done->errorCode);
    }

    public function testTickMarksTimeoutWhenHandlerOutlivesDeadline(): void
    {
        $this->dispatcher->dispatch('slow2', [], 7);
        $handler = new class($this->clock) implements TaskHandler {
            public function __construct(private FixedClock $clock)
            {
            }

            public function handle(TaskRun $task, TaskProgress $progress): ?array
            {
                $this->clock->advance(400);

                return null;
            }
        };
        $done = $this->executor(['slow2' => $handler], ['timeout_seconds' => 300])->tick();
        self::assertNotNull($done);
        self::assertSame(TaskState::Failed, $done->state);
        self::assertSame('timeout', $done->errorCode);
    }

    public function testFailedTaskStaysTerminal(): void
    {
        $this->dispatcher->dispatch('boom', [], 7);
        $executor = $this->executor([
            'boom' => new class implements TaskHandler {
                public function handle(TaskRun $task, TaskProgress $progress): ?array
                {
                    throw new \RuntimeException('always');
                }
            },
        ], ['max_attempts' => 2]);

        $done = $executor->tick();
        self::assertSame(TaskState::Failed, $done?->state);
        self::assertSame('handler_failed', $done->errorCode);
        // failed 是终态：不会被再次认领
        self::assertNull($executor->tick());
        self::assertSame(1, $done->attempt);
    }

    public function testLeaseExpiryAllowsReclaim(): void
    {
        $this->dispatcher->dispatch('noop', [], 7);
        $config = TaskConfig::fromArray(['lease_seconds' => 10, 'max_attempts' => 3]);
        $executor = new TaskExecutor(
            $this->store,
            new MappedTaskHandlerFactory(['noop' => new \Sandadmin\Task\Handler\NoopTaskHandler()]),
            $this->clock,
            'worker-a',
            $config,
        );
        // 直接把任务置为 running 且租约已过期（模拟执行者崩溃）
        $claimed = $this->store->claimNext('worker-dead', $this->clock->now(), $config->claimPolicy());
        self::assertNotNull($claimed);
        $this->clock->advance(60);
        $reclaimed = $this->store->claimNext('worker-b', $this->clock->now(), $config->claimPolicy());
        self::assertNotNull($reclaimed);
        self::assertSame($claimed->publicId, $reclaimed->publicId);
        self::assertSame(2, $reclaimed->attempt);
        self::assertSame('worker-b', $reclaimed->lockedBy);
    }

    public function testMaxAttemptsFailsExhaustedTask(): void
    {
        $this->dispatcher->dispatch('noop', [], 7);
        $config = TaskConfig::fromArray(['lease_seconds' => 10, 'max_attempts' => 1]);
        $first = $this->store->claimNext('w1', $this->clock->now(), $config->claimPolicy());
        self::assertNotNull($first);
        self::assertSame(1, $first->attempt);
        // 租约过期后再次认领：attempt+1 > maxAttempts，应直接判失败
        $this->clock->advance(60);
        $second = $this->store->claimNext('w2', $this->clock->now(), $config->claimPolicy());
        self::assertNull($second);
        $task = $this->store->findByPublicId($first->publicId);
        self::assertSame(TaskState::Failed, $task?->state);
        self::assertSame('max_attempts_exceeded', $task->errorCode);
    }

    public function testConcurrencyLimitDefersExcessTasks(): void
    {
        $this->dispatcher->dispatch('noop', [], 7);
        $this->dispatcher->dispatch('noop', [], 7);
        $config = TaskConfig::fromArray(['default_concurrency' => 1, 'lease_seconds' => 60]);
        $first = $this->store->claimNext('w1', $this->clock->now(), $config->claimPolicy());
        self::assertNotNull($first);
        $second = $this->store->claimNext('w2', $this->clock->now(), $config->claimPolicy());
        self::assertNull($second);
        // 第一个完成后第二个可被认领
        $this->store->markSucceeded($first->publicId, 'w1', null, $this->clock->now());
        $third = $this->store->claimNext('w2', $this->clock->now(), $config->claimPolicy());
        self::assertNotNull($third);
        self::assertNotSame($first->publicId, $third->publicId);
    }

    public function testPerTypeConcurrencyOverride(): void
    {
        $this->dispatcher->dispatch('slow.type', [], 7);
        $this->dispatcher->dispatch('fast.type', [], 7);
        $config = TaskConfig::fromArray([
            'default_concurrency' => 4,
            'concurrency' => ['slow.type' => 0],
            'lease_seconds' => 60,
        ]);
        $a = $this->store->claimNext('w1', $this->clock->now(), $config->claimPolicy());
        $b = $this->store->claimNext('w2', $this->clock->now(), $config->claimPolicy());
        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotSame($a->publicId, $b->publicId);
    }

    public function testProgressHeartbeatRenewsLease(): void
    {
        $this->dispatcher->dispatch('noop', [], 7);
        $config = TaskConfig::fromArray(['lease_seconds' => 10]);
        $claimed = $this->store->claimNext('w1', $this->clock->now(), $config->claimPolicy());
        self::assertNotNull($claimed);
        $this->clock->advance(8);
        $this->store->reportProgress($claimed->publicId, 'w1', '处理中', 50, $this->clock->now(), 10);
        $task = $this->store->findByPublicId($claimed->publicId);
        self::assertSame(50, $task?->progress);
        self::assertSame('处理中', $task->stage);
        self::assertNotNull($task->leaseExpireTime);
        // 8+10=18 秒后才过期；此时推进 9 秒（距心跳 9 秒）仍未过期
        $this->clock->advance(9);
        $reclaim = $this->store->claimNext('w2', $this->clock->now(), $config->claimPolicy());
        self::assertNull($reclaim);
    }

    public function testResultSizeLimit(): void
    {
        $this->dispatcher->dispatch('big', [], 7);
        $handler = new class implements TaskHandler {
            /**
             * @return array<string, mixed>
             */
            public function handle(TaskRun $task, TaskProgress $progress): array
            {
                return ['blob' => str_repeat('x', 300000)];
            }
        };
        $done = $this->executor(['big' => $handler])->tick();
        self::assertSame(TaskState::Failed, $done?->state);
        self::assertSame('invalid_result', $done->errorCode);
    }
}
