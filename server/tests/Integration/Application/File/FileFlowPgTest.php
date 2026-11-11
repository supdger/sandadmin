<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Application\File;

use PHPUnit\Framework\Attributes\Group;
use Sandadmin\Application\File\BoundedCopier;
use Sandadmin\Application\File\FileService;
use Sandadmin\Application\File\IdempotentReplay;
use Sandadmin\Application\File\StorageConfig;
use Sandadmin\Application\File\UploadService;
use Sandadmin\Domain\File\FileActor;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Infra\File\FinfoMimeSniffer;
use Sandadmin\Infra\File\LocalFileStorage;
use Sandadmin\Infra\File\PdoCleanupQueue;
use Sandadmin\Infra\File\PdoFileRepository;
use Sandadmin\Infra\Idempotency\PdoIdempotencyStore;
use Sandadmin\Tests\Integration\Support\PgFileCase;
use Sandadmin\Tests\Support\FileKit;

/**
 * 上传→元数据→幂等→下载鉴权的 PG 端到端验证。
 * 需要 SAND_TEST_DB_URL；存储对象落临时目录。
 */
#[Group('integration')]
final class FileFlowPgTest extends PgFileCase
{
    private string $root = '';

    private LocalFileStorage $storage;

    private PdoFileRepository $repository;

    private PdoIdempotencyStore $idempotency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = FileKit::scratchRoot();
        $this->storage = FileKit::storage($this->root);
        $this->repository = new PdoFileRepository(self::pdo());
        $this->idempotency = new PdoIdempotencyStore(self::pdo());
    }

    protected function tearDown(): void
    {
        FileKit::removeTree($this->root);
    }

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89";

    private function uploads(): UploadService
    {
        return new UploadService(
            $this->storage,
            $this->repository,
            FileKit::config(),
            new FinfoMimeSniffer(),
            new BoundedCopier(),
            $this->idempotency,
        );
    }

    private function service(): FileService
    {
        return new FileService($this->repository, $this->storage, new PdoCleanupQueue(self::pdo()), $this->idempotency);
    }

    public function testUploadPersistsMetadataUsageAndIdempotency(): void
    {
        $stream = FileKit::stream(self::PNG . 'flow');
        $record = $this->uploads()->upload(
            $stream, null, '流程图.png', FileVisibility::Public, null, self::$userId, 'flow-key'
        );
        fclose($stream);

        self::assertSame(FileKind::Image, $record->kind);
        self::assertTrue($this->storage->exists($record->storageKey));

        // 落库行可被仓储读回，usage_state=unknown（未登记用途）
        $found = $this->repository->findById($record->id);
        self::assertNotNull($found);
        self::assertSame('unknown', $found->usageState->value);
        self::assertSame(self::$userId, $found->uploaderId);

        // 同键同内容重放：返回首次 FileObject，不产生第二条记录
        $replay = FileKit::stream(self::PNG . 'flow');
        try {
            $this->uploads()->upload($replay, null, '流程图.png', FileVisibility::Public, null, self::$userId, 'flow-key');
            self::fail('应抛 IdempotentReplay');
        } catch (IdempotentReplay $signal) {
            // jsonb 往返后 response 为对象且不保序，按解码后内容比较
            $encoded = json_encode($signal->response);
            self::assertIsString($encoded);
            self::assertEquals($record->toApi(), json_decode($encoded, true));
        } finally {
            fclose($replay);
        }
        self::assertSame(1, $this->repository->search(new \Sandadmin\Domain\File\FileQuery(
            keyword: null, kind: null, usageState: null, uploaderId: null,
            createTimeFrom: null, createTimeTo: null, page: 1, pageSize: 20,
        ))->total);

        // 同键不同内容 → 409
        $other = FileKit::stream(self::PNG . 'different');
        try {
            $this->uploads()->upload($other, null, '流程图.png', FileVisibility::Public, null, self::$userId, 'flow-key');
            self::fail('应报幂等冲突');
        } catch (FileException $error) {
            self::assertSame('IDEMPOTENCY_KEY_REUSED', $error->errorCode);
        } finally {
            fclose($other);
        }
    }

    public function testUploadWithUsageRegistersFileUsageRow(): void
    {
        $usage = \Sandadmin\Domain\File\UsageDraft::parse('announcement:7');
        $stream = FileKit::stream(self::PNG);
        $record = $this->uploads()->upload(
            $stream, null, 'a.png', FileVisibility::Private, $usage, self::$userId
        );
        fclose($stream);

        $usages = $this->repository->usagesFor($record->id);
        self::assertCount(1, $usages);
        self::assertSame('announcement', $usages[0]->subjectType);
        self::assertSame('7', $usages[0]->subjectId);
        $found = $this->repository->findById($record->id);
        self::assertNotNull($found);
        self::assertSame('used', $found->usageState->value);
    }

    public function testDownloadAuthzAndDeleteFlow(): void
    {
        $service = $this->service();
        $access = new \Sandadmin\Tests\Support\FakeUsageAccess();

        // public 文件：任意登录用户可下
        $pub = FileKit::stream(self::PNG);
        $public = $this->uploads()->upload($pub, null, 'pub.png', FileVisibility::Public, null, self::$userId);
        fclose($pub);
        $record = $service->findForManagement($public->id);
        $service->assertDownloadable($record, new FileActor(self::$otherUserId, []), $access);
        $path = $service->downloadPath($record);
        self::assertFileExists($path);

        // private 文件：非上传人无 file:index → 403；上传人 → 通过
        $priv = FileKit::stream(self::PNG);
        $private = $this->uploads()->upload($priv, null, 'priv.png', FileVisibility::Private, null, self::$userId);
        fclose($priv);
        $record = $service->findForManagement($private->id);
        try {
            $service->assertDownloadable($record, new FileActor(self::$otherUserId, []), $access);
            self::fail('private 文件应拒绝非上传人');
        } catch (FileException $error) {
            self::assertSame('PERMISSION_DENIED', $error->errorCode);
        }
        $service->assertDownloadable($record, new FileActor(self::$userId, []), $access);

        // 删除：软删墓碑 + 对象清除 + 幂等重放
        $service->remove($private->id, self::$userId, 'pg-del-1');
        self::assertNull($this->repository->findById($private->id));
        try {
            $service->remove($private->id, self::$userId, 'pg-del-1');
            self::fail('应重放首次删除结果');
        } catch (IdempotentReplay) {
        }
    }

    public function testCleanupEnqueuesTaskRunRow(): void
    {
        $service = $this->service();
        $accepted = $service->cleanup(self::$userId, 'pg-clean-1');
        self::assertSame('queued', $accepted['state']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $accepted['task_id']);

        // 同键重放返回同一任务
        $again = $service->cleanup(self::$userId, 'pg-clean-1');
        self::assertSame($accepted['task_id'], $again['task_id']);
    }
}
