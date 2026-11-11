<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Unit\Task;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sandadmin\Task\InvalidTaskException;
use Sandadmin\Task\TaskDispatcher;
use Sandadmin\Task\TaskState;
use Sandadmin\Tests\Support\FakeTaskStore;
use Sandadmin\Tests\Support\FixedClock;

final class TaskDispatcherTest extends TestCase
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

    public function testDispatchCreatesQueuedTask(): void
    {
        $task = $this->dispatcher->dispatch('export.users', ['scope' => 'all'], 7, 'key-1');
        self::assertSame(TaskState::Queued, $task->state);
        self::assertSame('export.users', $task->type);
        self::assertSame(['scope' => 'all'], $task->payload);
        self::assertSame(7, $task->createdBy);
        self::assertSame('key-1', $task->idempotencyKey);
        self::assertSame(0, $task->progress);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/',
            $task->publicId
        );
        self::assertSame($task->publicId, $this->store->findByPublicId($task->publicId)?->publicId);
    }

    public function testDispatchValidatesTypeAndCreator(): void
    {
        $this->expectException(InvalidTaskException::class);
        $this->dispatcher->dispatch('Bad Type', [], 7);
    }

    public function testDispatchRejectsMissingCreator(): void
    {
        $this->expectException(InvalidTaskException::class);
        $this->dispatcher->dispatch('noop', [], 0);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('secretPayloads')]
    public function testDispatchRejectsSecretFields(array $payload): void
    {
        $this->expectException(InvalidTaskException::class);
        $this->dispatcher->dispatch('noop', $payload, 7);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function secretPayloads(): iterable
    {
        yield 'password' => [['password' => 'x']];
        yield 'nested api_key' => [['filter' => ['api_key' => 'x']]];
        yield 'list nested token' => [['items' => [['token' => 'x']]]];
        yield 'authorization' => [['headers' => ['Authorization' => 'Bearer x']]];
        yield 'app secret' => [['config' => ['app_secret' => 'x']]];
    }

    public function testDispatchAllowsOrdinaryPayload(): void
    {
        $task = $this->dispatcher->dispatch('noop', [
            'keyword' => '报告',
            'page' => 2,
            'filters' => ['dept_id' => 3],
        ], 7);
        self::assertSame(TaskState::Queued, $task->state);
    }

    public function testIdempotentDispatchReturnsExisting(): void
    {
        $first = $this->dispatcher->dispatch('noop', ['a' => 1], 7, 'same-key');
        $second = $this->dispatcher->dispatch('noop', ['a' => 2], 7, 'same-key');
        self::assertSame($first->publicId, $second->publicId);
        self::assertSame(1, $this->store->search(null, null, null, 1, 100)->total);
    }

    public function testSameKeyDifferentTypeIsSeparate(): void
    {
        $first = $this->dispatcher->dispatch('noop', [], 7, 'same-key');
        $second = $this->dispatcher->dispatch('other', [], 7, 'same-key');
        self::assertNotSame($first->publicId, $second->publicId);
    }

    public function testInvalidIdempotencyKeyRejected(): void
    {
        $this->expectException(InvalidTaskException::class);
        $this->dispatcher->dispatch('noop', [], 7, 'bad key with spaces');
    }
}
