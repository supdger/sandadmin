<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\IdempotentReplay;
use Sandadmin\Application\File\StorageConfig;
use Sandadmin\Domain\File\FileActor;
use Sandadmin\Domain\File\FileDraft;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FilePurpose;
use Sandadmin\Domain\File\FileUsage;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Domain\File\UnavailableUsageAccess;
use Sandadmin\Tests\Support\FakeCleanupQueue;
use Sandadmin\Tests\Support\FakeFileRepository;
use Sandadmin\Tests\Support\FakeIdempotencyStore;
use Sandadmin\Tests\Support\FakeUsageAccess;
use Sandadmin\Tests\Support\FileKit;

final class FileServiceTest extends TestCase
{
    private string $root = '';

    private FakeFileRepository $files;

    private \Sandadmin\Infra\File\LocalFileStorage $storage;

    protected function setUp(): void
    {
        $this->root = FileKit::scratchRoot();
        $this->files = new FakeFileRepository();
        $this->storage = FileKit::storage($this->root);
    }

    protected function tearDown(): void
    {
        FileKit::removeTree($this->root);
    }

    private function draft(string $key, FilePurpose $purpose = FilePurpose::Attachment, FileVisibility $visibility = FileVisibility::Private): FileDraft
    {
        return new FileDraft(
            originalName: 'doc.pdf',
            kind: FileKind::Document,
            mediaType: 'application/pdf',
            extension: 'pdf',
            sizeBytes: 10,
            sha256: str_repeat('a', 64),
            storageKey: $key,
            visibility: $visibility,
            purpose: $purpose,
            usageTracked: false,
            uploaderId: 7,
        );
    }

    private function seedFile(FilePurpose $purpose = FilePurpose::Attachment, FileVisibility $visibility = FileVisibility::Private): int
    {
        // 先落一个真实对象，保证 downloadPath/remove 的存储侧断言成立
        $stream = FileKit::stream('%PDF-1.4 fake');
        $key = $this->storage->writeStream($stream);
        fclose($stream);
        $draft = $this->draft($key, $purpose, $visibility);

        return $this->files->insert($draft);
    }

    public function testFindForManagementRejectsNonAttachment(): void
    {
        $service = FileKit::service($this->files, $this->storage);
        foreach ([FilePurpose::Export, FilePurpose::Package, FilePurpose::Plugin] as $purpose) {
            $id = $this->seedFile($purpose);
            try {
                $service->findForManagement($id);
                self::fail($purpose->value . ' 应按 FILE_NOT_FOUND 处理');
            } catch (FileException $error) {
                self::assertSame('FILE_NOT_FOUND', $error->errorCode);
                self::assertSame(404, $error->status);
            }
        }
    }

    public function testFindForManagementMissing(): void
    {
        $service = FileKit::service($this->files, $this->storage);
        $this->expectException(FileException::class);
        $service->findForManagement(999);
    }

    public function testDownloadPublicFileAllowedForAnyActor(): void
    {
        $service = FileKit::service($this->files, $this->storage);
        $id = $this->seedFile(FilePurpose::Attachment, FileVisibility::Public);
        $record = $service->findForManagement($id);
        $actor = new FileActor(999, []);

        $service->assertDownloadable($record, $actor, new UnavailableUsageAccess());
        self::assertSame(FileVisibility::Public, $record->visibility); // 无异常即通过
    }

    public function testDownloadPrivateFileUploaderOnly(): void
    {
        $service = FileKit::service($this->files, $this->storage);
        $id = $this->seedFile(FilePurpose::Attachment, FileVisibility::Private);
        $record = $service->findForManagement($id);

        // 上传人（uploader_id=7）放行
        $service->assertDownloadable($record, new FileActor(7, []), new UnavailableUsageAccess());
        // file:index 权限放行
        $service->assertDownloadable($record, new FileActor(8, ['system:file:index']), new UnavailableUsageAccess());
        // 其他登录用户拒绝
        try {
            $service->assertDownloadable($record, new FileActor(9, []), new UnavailableUsageAccess());
            self::fail('非上传人无权限下载 private 文件');
        } catch (FileException $error) {
            self::assertSame('PERMISSION_DENIED', $error->errorCode);
        }
    }

    public function testDownloadReferencedFileDelegatesToUsageAccess(): void
    {
        $service = FileKit::service($this->files, $this->storage);
        $id = $this->seedFile(FilePurpose::Attachment, FileVisibility::Public);
        $this->files->addUsage(new FileUsage($id, 'announcement', '1', '公告《测试》'));
        $record = $service->findForManagement($id);

        $deny = new FakeUsageAccess();
        $deny->allow = false;
        try {
            $service->assertDownloadable($record, new FileActor(1, ['*']), $deny);
            self::fail('引用方鉴权拒绝时应 403');
        } catch (FileException $error) {
            self::assertSame('PERMISSION_DENIED', $error->errorCode);
        }
        self::assertSame(1, $deny->calls[0]['usages']);

        $allow = new FakeUsageAccess();
        $service->assertDownloadable($record, new FileActor(1, []), $allow);
        self::assertTrue($allow->allow);
    }

    public function testReferencedFileWithUnavailableAccessDenied(): void
    {
        $service = FileKit::service($this->files, $this->storage);
        $id = $this->seedFile(FilePurpose::Attachment, FileVisibility::Public);
        $this->files->addUsage(new FileUsage($id, 'plugin_x', '9', '插件引用'));
        $record = $service->findForManagement($id);

        // 默认接缝：存在引用且无回调 → 拒绝（subject_type 无注册回调视为拒绝）
        try {
            $service->assertDownloadable($record, new FileActor(1, ['*']), new UnavailableUsageAccess());
            self::fail('无引用方回调时应拒绝');
        } catch (FileException $error) {
            self::assertSame('PERMISSION_DENIED', $error->errorCode);
        }
    }

    public function testRemoveSoftDeletesAndDeletesObject(): void
    {
        $cleanup = new FakeCleanupQueue();
        $service = FileKit::service($this->files, $this->storage, $cleanup);
        $id = $this->seedFile();
        $record = $this->files->findById($id);
        self::assertNotNull($record);
        self::assertTrue($this->storage->exists($record->storageKey));

        $service->remove($id, 7);

        self::assertNull($this->files->findById($id));
        self::assertFalse($this->storage->exists($record->storageKey));
        // 再删一次 → FILE_NOT_FOUND
        try {
            $service->remove($id, 7);
            self::fail('重复删除应 404');
        } catch (FileException $error) {
            self::assertSame('FILE_NOT_FOUND', $error->errorCode);
        }
    }

    public function testRemoveWithIdempotencyReplays(): void
    {
        $idempotency = new FakeIdempotencyStore();
        $service = FileKit::service($this->files, $this->storage, null, $idempotency);
        $id = $this->seedFile();

        $service->remove($id, 7, 'del-key-1');
        try {
            $service->remove($id, 7, 'del-key-1');
            self::fail('同键重复删除应重放首次结果');
        } catch (IdempotentReplay $replay) {
            self::assertEquals(new \stdClass(), $replay->response);
        }
    }

    public function testDownloadPathMissingObject(): void
    {
        $service = FileKit::service($this->files, $this->storage);
        // 元数据在、磁盘对象不在 → FILE_NOT_FOUND
        $id = $this->files->insert($this->draft('deadbeefdeadbeefdeadbeefdeadbeef'));
        $record = $this->files->findById($id);
        self::assertNotNull($record);

        $this->expectException(FileException::class);
        $service->downloadPath($record);
    }

    public function testCleanupDelegatesToQueue(): void
    {
        $cleanup = new FakeCleanupQueue();
        $service = FileKit::service($this->files, $this->storage, $cleanup);
        $accepted = $service->cleanup(7, 'clean-1');

        self::assertSame(FakeCleanupQueue::TASK_ID, $accepted['task_id']);
        self::assertSame('queued', $accepted['state']);
        self::assertSame(7, $cleanup->createdBy);
        self::assertSame('clean-1', $cleanup->idempotencyKey);
    }

    public function testUnusedUsageAccessDeniesOnlyWhenUsagesExist(): void
    {
        $access = new UnavailableUsageAccess();
        self::assertTrue($access->authorize(1, 1, []));
        self::assertFalse($access->authorize(1, 1, [new FileUsage(1, 'a', 'b', 'c')]));
    }
}
