<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Unit\Task;

use PHPUnit\Framework\TestCase;
use Sandadmin\Task\TaskActor;
use Sandadmin\Task\TaskArtifact;
use Sandadmin\Task\TaskArtifactResolver;
use Sandadmin\Task\TaskHttp;
use Sandadmin\Task\TaskRun;
use Sandadmin\Task\TaskState;
use Sandadmin\Tests\Support\FakeTaskStore;
use Sandadmin\Tests\Support\TaskRunFixture;

final class TaskHttpTest extends TestCase
{
    private FakeTaskStore $store;

    protected function setUp(): void
    {
        $this->store = new FakeTaskStore();
    }

    public function testIndexRequiresActor(): void
    {
        $outcome = TaskHttp::index($this->store, null, [], 'req-1');
        self::assertSame(401, $outcome['status']);
        self::assertSame('UNAUTHENTICATED', self::body($outcome)['code'] ?? null);
        self::assertSame('req-1', self::body($outcome)['request_id'] ?? null);
    }

    public function testIndexReturnsContractPage(): void
    {
        $mine = TaskRunFixture::make(createdBy: 7);
        $this->store->insert($mine);
        $this->store->insert(TaskRunFixture::make(createdBy: 9));

        $outcome = TaskHttp::index($this->store, new TaskActor(7, false), [], 'req-2');
        self::assertSame(200, $outcome['status']);
        $data = self::data($outcome);
        self::assertSame(1, $data['total'] ?? null);
        self::assertSame(1, $data['page'] ?? null);
        self::assertSame(20, $data['page_size'] ?? null);
        $items = self::items($outcome);
        self::assertCount(1, $items);
        self::assertSame($mine->publicId, self::firstItem($outcome)['id'] ?? null);
        self::assertSame('queued', self::firstItem($outcome)['state'] ?? null);
    }

    public function testIndexMineFlagDoesNotWidenVisibility(): void
    {
        $this->store->insert(TaskRunFixture::make(createdBy: 9));
        $actor = new TaskActor(7, false);
        foreach ([[], ['mine' => 'true'], ['mine' => 'false']] as $query) {
            $outcome = TaskHttp::index($this->store, $actor, $query, 'req-3');
            self::assertSame(0, self::data($outcome)['total'] ?? null, 'mine=' . var_export($query, true));
        }
    }

    public function testIndexAdminSeesAllUnlessMine(): void
    {
        $own = TaskRunFixture::make(createdBy: 1);
        $this->store->insert(TaskRunFixture::make(createdBy: 7));
        $this->store->insert(TaskRunFixture::make(createdBy: 9));
        $this->store->insert($own);
        $admin = new TaskActor(1, true);

        $all = TaskHttp::index($this->store, $admin, [], 'r');
        self::assertSame(3, self::data($all)['total'] ?? null);
        $onlyMine = TaskHttp::index($this->store, $admin, ['mine' => 'true'], 'r');
        self::assertSame(1, self::data($onlyMine)['total'] ?? null);
        self::assertSame($own->publicId, self::firstItem($onlyMine)['id'] ?? null);
    }

    public function testIndexValidatesQuery(): void
    {
        $outcome = TaskHttp::index(
            $this->store,
            new TaskActor(7, false),
            ['state' => 'bogus', 'page_size' => '500'],
            'r',
        );
        self::assertSame(400, $outcome['status']);
        self::assertSame('VALIDATION_FAILED', self::body($outcome)['code'] ?? null);
        $fields = self::fields($outcome);
        self::assertArrayHasKey('state', $fields);
        self::assertArrayHasKey('page_size', $fields);
    }

    public function testIndexFiltersByTypeAndState(): void
    {
        $this->store->insert(TaskRunFixture::make(type: 'noop', state: TaskState::Queued));
        $this->store->insert(TaskRunFixture::make(type: 'export', state: TaskState::Succeeded, createdBy: 7));

        $admin = new TaskActor(1, true);
        $byType = TaskHttp::index($this->store, $admin, ['type' => 'export'], 'r');
        self::assertSame(1, self::data($byType)['total'] ?? null);
        self::assertSame('export', self::firstItem($byType)['type'] ?? null);
        $byState = TaskHttp::index($this->store, $admin, ['state' => 'queued'], 'r');
        self::assertSame(1, self::data($byState)['total'] ?? null);
    }

    public function testShowReturnsTaskOrHidesInvisible(): void
    {
        $task = TaskRunFixture::make(
            createdBy: 9,
            state: TaskState::Running,
            stage: '导出中',
            progress: 40,
        );
        $this->store->insert($task);

        $hidden = TaskHttp::show($this->store, new TaskActor(7, false), $task->publicId, 'r');
        self::assertSame(404, $hidden['status']);
        self::assertSame('TASK_RUN_NOT_FOUND', self::body($hidden)['code'] ?? null);

        $shown = TaskHttp::show($this->store, new TaskActor(9, false), $task->publicId, 'r');
        self::assertSame(200, $shown['status']);
        $data = self::data($shown);
        self::assertSame($task->publicId, $data['id'] ?? null);
        self::assertSame('running', $data['state'] ?? null);
        self::assertSame('导出中', $data['stage'] ?? null);
        self::assertSame(40, $data['progress'] ?? null);

        self::assertSame(200, TaskHttp::show($this->store, new TaskActor(1, true), $task->publicId, 'r')['status']);
    }

    public function testShowRejectsMalformedId(): void
    {
        $outcome = TaskHttp::show($this->store, new TaskActor(7, false), 'not-a-uuid', 'r');
        self::assertSame(400, $outcome['status']);
        self::assertSame('VALIDATION_FAILED', self::body($outcome)['code'] ?? null);
    }

    public function testTaskShapeMatchesContract(): void
    {
        $task = TaskRunFixture::make(
            state: TaskState::Failed,
            stage: '执行失败',
            errorCode: 'handler_failed',
            errorMessage: '任务执行失败',
            finishTime: new \DateTimeImmutable('2026-11-10 01:00:00', new \DateTimeZone('UTC')),
            startTime: new \DateTimeImmutable('2026-11-10 00:30:00', new \DateTimeZone('UTC')),
            result: ['file_id' => 12],
        );
        $shape = TaskHttp::task($task);
        self::assertSame($task->publicId, $shape['id']);
        self::assertSame('failed', $shape['state']);
        self::assertSame(['code' => 'handler_failed', 'message' => '任务执行失败'], $shape['error']);
        self::assertSame(['file_id' => 12], $shape['result']);
        self::assertIsString($shape['create_time']);
        self::assertStringContainsString('2026-11-10', (string) $shape['create_time']);
        self::assertNotNull($shape['start_time']);
        self::assertNotNull($shape['finish_time']);
    }

    public function testDownloadRequiresSucceededTaskAndArtifact(): void
    {
        $task = TaskRunFixture::make(state: TaskState::Succeeded, result: ['file_id' => 5], createdBy: 7);
        $this->store->insert($task);
        $actor = new TaskActor(7, false);

        $noArtifact = TaskHttp::download($this->store, $actor, $task->publicId, self::nullResolver(), 'r');
        self::assertSame(404, $noArtifact['status']);
        self::assertSame('TASK_RUN_NOT_DOWNLOADABLE', self::body($noArtifact)['code'] ?? null);

        $withArtifact = TaskHttp::download($this->store, $actor, $task->publicId, self::fileResolver(), 'r');
        self::assertSame(200, $withArtifact['status']);
        self::assertInstanceOf(TaskArtifact::class, $withArtifact['artifact'] ?? null);
    }

    public function testDownloadRejectsRunningTask(): void
    {
        $task = TaskRunFixture::make(state: TaskState::Running, createdBy: 7);
        $this->store->insert($task);
        $outcome = TaskHttp::download(
            $this->store,
            new TaskActor(7, false),
            $task->publicId,
            self::fileResolver(),
            'r',
        );
        self::assertSame(404, $outcome['status']);
        self::assertSame('TASK_RUN_NOT_DOWNLOADABLE', self::body($outcome)['code'] ?? null);
    }

    public function testDownloadHidesInvisibleTask(): void
    {
        $task = TaskRunFixture::make(state: TaskState::Succeeded, createdBy: 9);
        $this->store->insert($task);
        $outcome = TaskHttp::download(
            $this->store,
            new TaskActor(7, false),
            $task->publicId,
            self::fileResolver(),
            'r',
        );
        self::assertSame(404, $outcome['status']);
        self::assertSame('TASK_RUN_NOT_FOUND', self::body($outcome)['code'] ?? null);
    }

    private static function nullResolver(): TaskArtifactResolver
    {
        return new class implements TaskArtifactResolver {
            public function resolve(TaskRun $task): ?TaskArtifact
            {
                return null;
            }
        };
    }

    private static function fileResolver(): TaskArtifactResolver
    {
        return new class implements TaskArtifactResolver {
            public function resolve(TaskRun $task): TaskArtifact
            {
                return new TaskArtifact(5, 'export.csv', 'text/csv', 128, '/tmp/export.csv');
            }
        };
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<array-key, mixed>
     */
    private static function body(array $outcome): array
    {
        $body = $outcome['body'] ?? null;
        if (!is_array($body)) {
            self::fail('响应缺少 body');
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<array-key, mixed>
     */
    private static function data(array $outcome): array
    {
        $data = self::body($outcome)['data'] ?? null;
        if (!is_array($data)) {
            self::fail('响应缺少 data');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<array-key, mixed>
     */
    private static function items(array $outcome): array
    {
        $items = self::data($outcome)['items'] ?? null;
        if (!is_array($items)) {
            self::fail('响应缺少 items');
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<array-key, mixed>
     */
    private static function firstItem(array $outcome): array
    {
        $item = self::items($outcome)[0] ?? null;
        if (!is_array($item)) {
            self::fail('items 为空');
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<array-key, mixed>
     */
    private static function fields(array $outcome): array
    {
        $fields = self::body($outcome)['fields'] ?? null;
        if (!is_array($fields)) {
            self::fail('fields 不是对象');
        }

        return $fields;
    }
}
