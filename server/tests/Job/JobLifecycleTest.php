<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Tests\Job;

use PHPUnit\Framework\TestCase;
use Sandadmin\Job\Handler\NoopJobHandler;
use Sandadmin\Job\InvalidJobException;
use Sandadmin\Job\JobConnection;
use Sandadmin\Job\JobDispatcher;
use Sandadmin\Job\JobExecutor;
use Sandadmin\Job\JobHandler;
use Sandadmin\Job\JobHttp;
use Sandadmin\Job\JobProgress;
use Sandadmin\Job\JobRun;
use Sandadmin\Job\JobStatus;
use Sandadmin\Job\JobWorker;
use Sandadmin\Job\MappedJobHandlerFactory;
use Sandadmin\Job\MissingJobSchemaException;
use Sandadmin\Job\PdoJobStore;
use ReflectionClass;

final class JobLifecycleTest extends TestCase
{
    public function testNoopJobReachesSucceeded(): void
    {
        $runtime = $this->runtime();
        $job = $runtime['dispatcher']->dispatch('noop', ['source' => 'test']);
        self::assertSame(JobStatus::Pending, $job->status);

        $done = $runtime['executor']->tick();
        self::assertInstanceOf(JobRun::class, $done);
        self::assertTrue($done->status->isTerminal());
        self::assertSame(JobStatus::Succeeded, $done->status);
        self::assertSame('completed', $done->stage);
        self::assertSame(100, $done->progress);
        self::assertSame(['ok' => true], $done->result);
        self::assertNull($done->error);
        self::assertNull($runtime['executor']->tick());
    }

    public function testProgressIsVisibleBeforeCompletion(): void
    {
        $store = $this->store();
        $store->ensureSchema();
        $clock = new FixedClock();
        $handler = new class ($store) implements JobHandler {
            public ?int $seen = null;

            public function __construct(private readonly PdoJobStore $store)
            {
            }

            public function handle(JobRun $job, JobProgress $progress): ?array
            {
                $progress->report('halfway', 50);
                $seen = $this->store->find($job->id);
                $this->seen = $seen?->progress;

                return ['done' => true];
            }
        };
        $dispatcher = new JobDispatcher($store, new SequenceIdGenerator(), $clock);
        $executor = new JobExecutor($store, new MappedJobHandlerFactory([
            'spy' => $handler,
        ]), $clock, 'worker-a');

        $dispatcher->dispatch('spy');
        $done = $executor->tick();

        self::assertSame(50, $handler->seen);
        self::assertNotNull($done);
        self::assertSame(JobStatus::Succeeded, $done->status);
        self::assertSame(100, $done->progress);
    }

    public function testHandlerFailureIsTerminal(): void
    {
        $store = $this->store();
        $store->ensureSchema();
        $clock = new FixedClock();
        $failing = new class implements JobHandler {
            public function handle(JobRun $job, JobProgress $progress): ?array
            {
                $progress->report('working', 10);
                throw new \RuntimeException("bad\ninput");
            }
        };
        $dispatcher = new JobDispatcher($store, new SequenceIdGenerator(), $clock);
        $executor = new JobExecutor($store, new MappedJobHandlerFactory([
            'boom' => $failing,
        ]), $clock, 'worker-a');
        $job = $dispatcher->dispatch('boom');
        $done = $executor->tick();

        self::assertNotNull($done);
        self::assertSame(JobStatus::Failed, $done->status);
        self::assertTrue($done->status->isTerminal());
        self::assertSame('failed', $done->stage);
        self::assertSame('bad input', $done->error);
        self::assertSame(10, $done->progress);
        self::assertSame($job->id, $done->id);
    }

    public function testUnknownTypeFailsWithoutLeavingTheJobRunning(): void
    {
        $runtime = $this->runtime();
        $job = $runtime['dispatcher']->dispatch('missing');
        $done = $runtime['executor']->tick();

        self::assertNotNull($done);
        self::assertSame($job->id, $done->id);
        self::assertSame(JobStatus::Failed, $done->status);
        self::assertStringContainsString('未知任务类型', (string) $done->error);
    }

    public function testSecondWorkerDoesNotClaimARunningJob(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jobrun');
        self::assertIsString($path);
        try {
            $first = $this->store($path);
            $second = $this->store($path);
            $first->ensureSchema();
            $clock = new FixedClock();
            $dispatcher = new JobDispatcher($first, new SequenceIdGenerator(), $clock);
            $dispatcher->dispatch('noop');

            $claimed = $first->claimNext('worker-a', $clock->now());
            self::assertNotNull($claimed);
            self::assertSame(JobStatus::Running, $claimed->status);
            self::assertNull($second->claimNext('worker-b', $clock->now()));
        } finally {
            @unlink($path);
        }
    }

    public function testPollingShapeAndUnknownId(): void
    {
        $runtime = $this->runtime();
        $job = $runtime['dispatcher']->dispatch('noop');
        $runtime['executor']->tick();

        $ok = JobHttp::show($runtime['store'], $job->id, 'req-12345678');
        self::assertSame(200, $ok['status']);
        self::assertSame($job->id, $ok['body']['id']);
        self::assertSame('succeeded', $ok['body']['status']);
        self::assertSame(100, $ok['body']['progress']);
        self::assertArrayNotHasKey('payload', $ok['body']);

        $missing = JobHttp::show($runtime['store'], '00000000-0000-4000-8000-000000000099', 'req-12345678');
        self::assertSame(404, $missing['status']);
        self::assertSame('job_not_found', $missing['body']['code']);
        self::assertSame('req-12345678', $missing['body']['request_id']);
        self::assertIsObject($missing['body']['fields']);

        $invalid = JobHttp::show($runtime['store'], '../etc/passwd', 'req-12345678');
        self::assertSame(400, $invalid['status']);
        self::assertSame('invalid_job_id', $invalid['body']['code']);
    }

    public function testMissingSchemaIsReportedWithoutQueryDetails(): void
    {
        $store = $this->store();
        try {
            $store->find('00000000-0000-4000-8000-000000000001');
            self::fail('expected missing schema');
        } catch (MissingJobSchemaException) {
            $outcome = JobHttp::show($store, '00000000-0000-4000-8000-000000000001', 'req-12345678');
        }
        self::assertSame(503, $outcome['status']);
        self::assertSame('job_store_unavailable', $outcome['body']['code']);
        self::assertStringNotContainsString('SQLSTATE', (string) $outcome['body']['message']);
    }

    public function testDispatchRejectsBadType(): void
    {
        $runtime = $this->runtime();
        $this->expectException(InvalidJobException::class);
        $runtime['dispatcher']->dispatch('Noop');
    }

    public function testDispatchRejectsListPayload(): void
    {
        $runtime = $this->runtime();
        $this->expectException(InvalidJobException::class);
        /** @var array<string, mixed> $payload */
        $payload = ['a', 'b'];
        $runtime['dispatcher']->dispatch('noop', $payload);
    }

    public function testEnsureSchemaIsIdempotent(): void
    {
        $store = $this->store();
        $store->ensureSchema();
        $store->ensureSchema();
        $runtime = [
            'dispatcher' => new JobDispatcher($store, new SequenceIdGenerator(), new FixedClock()),
        ];
        $job = $runtime['dispatcher']->dispatch('noop');
        self::assertSame(JobStatus::Pending, $store->find($job->id)?->status);
    }

    public function testHostWiresWorkerAndPollingRoute(): void
    {
        $root = dirname(__DIR__, 2);
        $process = file_get_contents($root . '/config/process.php');
        $route = file_get_contents($root . '/config/route.php');
        $migration = file_get_contents($root . '/database/migrations/2026_11_05_0001_create_job_run.sql');
        self::assertIsString($process);
        self::assertIsString($route);
        self::assertIsString($migration);
        self::assertStringContainsString('Sandadmin\\Job\\JobWorker::class', $process);
        self::assertStringContainsString('/api/jobs/{id}', $route);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS job_run', $migration);
        self::assertStringContainsString('FOR UPDATE SKIP LOCKED', file_get_contents($root . '/src/Job/PdoJobStore.php') ?: '');
    }

    public function testCoreClassesHaveNoStaticProperties(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $checked = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertStringContainsString('declare(strict_types=1);', $contents);
            self::assertDoesNotMatchRegularExpression('/\bstatic\s+\$/', $contents);
            $checked++;
        }
        self::assertGreaterThan(10, $checked);
        self::assertSame([], (new ReflectionClass(JobWorker::class))->getStaticProperties());
    }

    /**
     * @return array{store: PdoJobStore, dispatcher: JobDispatcher, executor: JobExecutor}
     */
    private function runtime(): array
    {
        $store = $this->store();
        $store->ensureSchema();
        $clock = new FixedClock();
        $dispatcher = new JobDispatcher($store, new SequenceIdGenerator(), $clock);
        $executor = new JobExecutor(
            $store,
            new MappedJobHandlerFactory(['noop' => new NoopJobHandler()]),
            $clock,
            'worker-a',
        );

        return ['store' => $store, 'dispatcher' => $dispatcher, 'executor' => $executor];
    }

    private function store(?string $path = null): PdoJobStore
    {
        $database = $path ?? ':memory:';
        $pdo = JobConnection::open([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => $database,
                    'username' => '',
                    'password' => '',
                ],
            ],
        ]);

        return new PdoJobStore($pdo);
    }
}
