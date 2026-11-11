<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\FileHttp;
use Sandadmin\Application\File\UploadService;
use Sandadmin\Domain\File\FileActor;
use Sandadmin\Domain\File\FileDraft;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FilePurpose;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Tests\Support\FakeCleanupQueue;
use Sandadmin\Tests\Support\FakeFileRepository;
use Sandadmin\Tests\Support\FakeIdempotencyStore;
use Sandadmin\Tests\Support\FakeUsageAccess;
use Sandadmin\Tests\Support\FileKit;

final class FileHttpTest extends TestCase
{
    private string $root = '';

    private FakeFileRepository $files;

    private \Sandadmin\Infra\File\LocalFileStorage $storage;

    private FakeCleanupQueue $cleanup;

    private FakeIdempotencyStore $idempotency;

    protected function setUp(): void
    {
        $this->root = FileKit::scratchRoot();
        $this->files = new FakeFileRepository();
        $this->storage = FileKit::storage($this->root);
        $this->cleanup = new FakeCleanupQueue();
        $this->idempotency = new FakeIdempotencyStore();
    }

    protected function tearDown(): void
    {
        FileKit::removeTree($this->root);
    }

    private function service(): \Sandadmin\Application\File\FileService
    {
        return FileKit::service($this->files, $this->storage, $this->cleanup, $this->idempotency);
    }

    private function uploads(): UploadService
    {
        return FileKit::uploads($this->storage, $this->files, $this->idempotency);
    }

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89";

    private function seed(): int
    {
        $stream = FileKit::stream(self::PNG);
        $key = $this->storage->writeStream($stream);
        fclose($stream);
        $draft = new FileDraft(
            originalName: 'pic.png',
            kind: FileKind::Image,
            mediaType: 'image/png',
            extension: 'png',
            sizeBytes: 8,
            sha256: str_repeat('b', 64),
            storageKey: $key,
            visibility: FileVisibility::Public,
            purpose: FilePurpose::Attachment,
            usageTracked: false,
            uploaderId: 7,
        );

        return $this->files->insert($draft);
    }

    private static function admin(): FileActor
    {
        return new FileActor(1, ['*']);
    }

    public function testUploadRequiresActor(): void
    {
        $stream = FileKit::stream(self::PNG);
        $outcome = FileHttp::upload($this->uploads(), null, $stream, null, 'a.png', null, null, null, 'req-1');
        fclose($stream);

        self::assertSame(401, $outcome['status']);
        self::assertSame('UNAUTHENTICATED', $outcome['body']['code']);
        self::assertSame('req-1', $outcome['body']['request_id']);
    }

    public function testUploadRejectsBadVisibilityAndUsage(): void
    {
        $stream = FileKit::stream(self::PNG);
        $outcome = FileHttp::upload($this->uploads(), self::admin(), $stream, null, 'a.png', 'world', 'badformat', null, 'req-2');
        fclose($stream);

        self::assertSame(400, $outcome['status']);
        self::assertSame('VALIDATION_FAILED', $outcome['body']['code']);
        $fields = $outcome['body']['fields'] ?? null;
        self::assertIsArray($fields);
        self::assertArrayHasKey('visibility', $fields);
        self::assertArrayHasKey('usage', $fields);
    }

    public function testUploadSuccessEnvelope(): void
    {
        $stream = FileKit::stream(self::PNG);
        $outcome = FileHttp::upload(
            $this->uploads(), self::admin(), $stream, null, 'a.png', 'public', 'article:9', 'idem-9', 'req-3'
        );
        fclose($stream);

        self::assertSame(200, $outcome['status']);
        $data = $outcome['body']['data'];
        self::assertIsArray($data);
        self::assertSame('image/png', $data['media_type']);
        self::assertSame('public', $data['visibility']);
        self::assertSame('attachment', $data['purpose']);
        self::assertSame('used', $data['usage_state']);
        self::assertSame('req-3', $outcome['body']['request_id']);
        // FileObject 字段集与契约一致：不含 extension/sha256/storage_key
        self::assertEquals(
            ['id', 'original_name', 'kind', 'media_type', 'size_bytes', 'usage_state',
                'usage_label', 'visibility', 'purpose', 'uploader_id', 'uploader_name', 'create_time'],
            array_keys($data),
        );
    }

    public function testIndexRequiresPermission(): void
    {
        $outcome = FileHttp::index($this->service(), new FileActor(2, []), [], 'req-4');
        self::assertSame(403, $outcome['status']);
        self::assertSame('PERMISSION_DENIED', $outcome['body']['code']);
    }

    public function testIndexValidatesQuery(): void
    {
        $outcome = FileHttp::index(
            $this->service(), self::admin(),
            ['kind' => 'bogus', 'page_size' => '999', 'usage_state' => 'weird'],
            'req-5',
        );
        self::assertSame(400, $outcome['status']);
        $fields = $outcome['body']['fields'] ?? null;
        self::assertIsArray($fields);
        self::assertArrayHasKey('kind', $fields);
        self::assertArrayHasKey('page_size', $fields);
        self::assertArrayHasKey('usage_state', $fields);
    }

    public function testIndexReturnsPageFile(): void
    {
        $this->seed();
        $outcome = FileHttp::index($this->service(), self::admin(), ['keyword' => 'pic'], 'req-6');

        self::assertSame(200, $outcome['status']);
        $data = $outcome['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame(1, $data['total'] ?? null);
        self::assertSame(1, $data['page'] ?? null);
        self::assertSame(20, $data['page_size'] ?? null);
        $items = $data['items'] ?? null;
        self::assertIsArray($items);
        self::assertCount(1, $items);
        $first = $items[0] ?? null;
        self::assertIsArray($first);
        self::assertSame('pic.png', $first['original_name'] ?? null);
    }

    public function testShowBadAndMissingId(): void
    {
        $outcome = FileHttp::show($this->service(), self::admin(), 'abc', 'req-7');
        self::assertSame(400, $outcome['status']);

        $outcome = FileHttp::show($this->service(), self::admin(), '424242', 'req-8');
        self::assertSame(404, $outcome['status']);
        self::assertSame('FILE_NOT_FOUND', $outcome['body']['code']);
    }

    public function testDestroyValidatesAndDeletes(): void
    {
        $outcome = FileHttp::destroy($this->service(), self::admin(), 'x', null, 'req-9');
        self::assertSame(400, $outcome['status']);

        $id = $this->seed();
        $outcome = FileHttp::destroy($this->service(), self::admin(), (string) $id, 'del-1', 'req-10');
        self::assertSame(200, $outcome['status']);
        self::assertEquals(new \stdClass(), $outcome['body']['data']);
        // 幂等重放：同键再删返回首次结果而不是 404
        $replay = FileHttp::destroy($this->service(), self::admin(), (string) $id, 'del-1', 'req-11');
        self::assertSame(200, $replay['status']);
        self::assertSame('req-11', $replay['body']['request_id']);
    }

    public function testDestroyRequiresDeletePermission(): void
    {
        $id = $this->seed();
        $outcome = FileHttp::destroy(
            $this->service(), new FileActor(2, ['system:file:index']), (string) $id, null, 'req-12'
        );
        self::assertSame(403, $outcome['status']);
        self::assertSame('PERMISSION_DENIED', $outcome['body']['code']);
    }

    public function testDownloadRoutesByPurpose(): void
    {
        // attachment 可下；export/package/plugin 按 FILE_NOT_FOUND
        $id = $this->seed();
        $access = new FakeUsageAccess();
        $outcome = FileHttp::download($this->service(), $access, self::admin(), (string) $id, 'req-13');
        self::assertSame(200, $outcome['status']);
        $file = $outcome['file'] ?? null;
        self::assertIsArray($file);
        self::assertSame('pic.png', $file['name']);
        self::assertSame('image/png', $file['media_type']);
        self::assertArrayNotHasKey('body', $outcome);

        $keys = ['export' => '1', 'package' => '2', 'plugin' => '3'];
        foreach ([FilePurpose::Export, FilePurpose::Package, FilePurpose::Plugin] as $purpose) {
            $other = $this->files->insert(new FileDraft(
                originalName: 'x.bin', kind: FileKind::Other, mediaType: 'application/octet-stream',
                extension: 'bin', sizeBytes: 1, sha256: str_repeat('c', 64),
                storageKey: str_repeat($keys[$purpose->value], 32),
                visibility: FileVisibility::Public, purpose: $purpose,
                usageTracked: false, uploaderId: 7,
            ));
            $denied = FileHttp::download($this->service(), $access, self::admin(), (string) $other, 'req-14');
            self::assertSame(404, $denied['status'], $purpose->value);
            $deniedBody = $denied['body'] ?? null;
            self::assertIsArray($deniedBody);
            self::assertSame('FILE_NOT_FOUND', $deniedBody['code'] ?? null);
        }
    }

    public function testDownloadRequiresActor(): void
    {
        $id = $this->seed();
        $outcome = FileHttp::download($this->service(), new FakeUsageAccess(), null, (string) $id, 'req-15');
        self::assertSame(401, $outcome['status']);
    }

    public function testCleanupRequiresPermissionAndReturnsTaskAccepted(): void
    {
        $denied = FileHttp::cleanup($this->service(), new FileActor(2, []), null, 'req-16');
        self::assertSame(403, $denied['status']);

        $outcome = FileHttp::cleanup($this->service(), self::admin(), 'clean-key', 'req-17');
        self::assertSame(200, $outcome['status']);
        $data = $outcome['body']['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame(FakeCleanupQueue::TASK_ID, $data['task_id'] ?? null);
        self::assertSame('queued', $data['state'] ?? null);
        self::assertSame('clean-key', $this->cleanup->idempotencyKey);
        self::assertSame(1, $this->cleanup->createdBy);

        $bad = FileHttp::cleanup($this->service(), self::admin(), 'bad key!', 'req-18');
        self::assertSame(400, $bad['status']);
    }
}
