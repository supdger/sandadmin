<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\IdempotentReplay;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FilePurpose;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Domain\File\UsageDraft;
use Sandadmin\Domain\File\FileUsageState;
use Sandadmin\Tests\Support\FakeFileRepository;
use Sandadmin\Tests\Support\FakeIdempotencyStore;
use Sandadmin\Tests\Support\FileKit;

final class UploadServiceTest extends TestCase
{
    private string $root = '';

    private FakeFileRepository $files;

    protected function setUp(): void
    {
        $this->root = FileKit::scratchRoot();
        $this->files = new FakeFileRepository();
    }

    protected function tearDown(): void
    {
        FileKit::removeTree($this->root);
    }

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89";

    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    public function testUploadPngStoresMetadataAndObject(): void
    {
        $storage = FileKit::storage($this->root);
        $uploads = FileKit::uploads($storage, $this->files);
        $stream = FileKit::stream(self::PNG . random_bytes(64));

        $record = $uploads->upload($stream, null, '../../evil/我的图.PNG', FileVisibility::Private, null, 42);
        fclose($stream);

        self::assertSame(FileKind::Image, $record->kind);
        self::assertSame('image/png', $record->mediaType);
        self::assertSame('png', $record->extension);
        self::assertSame('我的图.PNG', $record->originalName);
        self::assertSame(FileVisibility::Private, $record->visibility);
        self::assertSame(FilePurpose::Attachment, $record->purpose);
        self::assertFalse($record->usageTracked);
        self::assertSame(FileUsageState::Unknown, $record->usageState);
        self::assertSame(42, $record->uploaderId);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $record->sha256);
        self::assertTrue($storage->exists($record->storageKey));
        // 对象键是随机串，不含原始文件名
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $record->storageKey);
    }

    public function testUploadPdfClassifiedDocument(): void
    {
        $storage = FileKit::storage($this->root);
        $uploads = FileKit::uploads($storage, $this->files);
        $stream = FileKit::stream(self::PDF);

        $record = $uploads->upload($stream, null, 'report.pdf', FileVisibility::Public, null, 1);
        fclose($stream);

        self::assertSame(FileKind::Document, $record->kind);
        self::assertSame('application/pdf', $record->mediaType);
        self::assertSame(FileVisibility::Public, $record->visibility);
    }

    public function testRejectsDisallowedMime(): void
    {
        $storage = FileKit::storage($this->root);
        $uploads = FileKit::uploads($storage, $this->files);
        // ELF 魔数 → application/x-executable，不在白名单
        $stream = FileKit::stream("\x7fELF" . random_bytes(128));

        try {
            $uploads->upload($stream, null, 'run.elf', FileVisibility::Private, null, 1);
            self::fail('应拒绝不允许的 MIME');
        } catch (FileException $error) {
            self::assertSame('FILE_TYPE_NOT_ALLOWED', $error->errorCode);
            self::assertSame(422, $error->status);
        } finally {
            fclose($stream);
        }
    }

    public function testRejectsDisallowedExtension(): void
    {
        $storage = FileKit::storage($this->root);
        $uploads = FileKit::uploads($storage, $this->files);
        // 内容合法但扩展名 exe 不在白名单
        $stream = FileKit::stream(self::PNG);

        try {
            $uploads->upload($stream, null, 'evil.exe', FileVisibility::Private, null, 1);
            self::fail('应拒绝不允许的扩展名');
        } catch (FileException $error) {
            self::assertSame('FILE_TYPE_NOT_ALLOWED', $error->errorCode);
        } finally {
            fclose($stream);
        }
    }

    public function testRejectsTooLarge(): void
    {
        $storage = FileKit::storage($this->root);
        $uploads = FileKit::uploads($storage, $this->files, null, 64);
        $stream = FileKit::stream(str_repeat('x', 200));

        try {
            $uploads->upload($stream, 200, 'big.txt', FileVisibility::Private, null, 1);
            self::fail('应拒绝超限文件');
        } catch (FileException $error) {
            self::assertSame('FILE_TOO_LARGE', $error->errorCode);
            self::assertSame(413, $error->status);
        } finally {
            fclose($stream);
        }
    }

    public function testUsageDraftMarksTrackedAndRegistersUsage(): void
    {
        $storage = FileKit::storage($this->root);
        $uploads = FileKit::uploads($storage, $this->files);
        $stream = FileKit::stream(self::PDF);
        $usage = UsageDraft::parse('announcement:42');

        $record = $uploads->upload($stream, null, 'a.pdf', FileVisibility::Private, $usage, 9);
        fclose($stream);

        self::assertTrue($record->usageTracked);
        self::assertSame(FileUsageState::Used, $record->usageState);
        self::assertSame('announcement:42', $record->usageLabel);
        $usages = $this->files->usagesFor($record->id);
        self::assertCount(1, $usages);
        self::assertSame('announcement', $usages[0]->subjectType);
        self::assertSame('42', $usages[0]->subjectId);
    }

    public function testIdempotentReplayReturnsFirstResult(): void
    {
        $storage = FileKit::storage($this->root);
        $idempotency = new FakeIdempotencyStore();
        $uploads = FileKit::uploads($storage, $this->files, $idempotency);
        $content = self::PNG . 'payload';

        $firstStream = FileKit::stream($content);
        $first = $uploads->upload($firstStream, null, 'a.png', FileVisibility::Private, null, 5, 'key-1');
        fclose($firstStream);

        $secondStream = FileKit::stream($content);
        try {
            $uploads->upload($secondStream, null, 'a.png', FileVisibility::Private, null, 5, 'key-1');
            self::fail('第二次同键上传应抛 IdempotentReplay');
        } catch (IdempotentReplay $replay) {
            self::assertSame($first->toApi(), $replay->response);
        } finally {
            fclose($secondStream);
        }
        // 重放不产生第二条文件记录
        self::assertSame(1, $this->files->search(new \Sandadmin\Domain\File\FileQuery(
            keyword: null, kind: null, usageState: null, uploaderId: null,
            createTimeFrom: null, createTimeTo: null, page: 1, pageSize: 20,
        ))->total);
    }

    public function testIdempotencyConflictOnDifferentContent(): void
    {
        $storage = FileKit::storage($this->root);
        $idempotency = new FakeIdempotencyStore();
        $uploads = FileKit::uploads($storage, $this->files, $idempotency);

        $first = FileKit::stream(self::PNG);
        $uploads->upload($first, null, 'a.png', FileVisibility::Private, null, 5, 'key-2');
        fclose($first);

        $second = FileKit::stream(self::PDF);
        try {
            $uploads->upload($second, null, 'a.png', FileVisibility::Private, null, 5, 'key-2');
            self::fail('同键不同内容应报 409');
        } catch (FileException $error) {
            self::assertSame('IDEMPOTENCY_KEY_REUSED', $error->errorCode);
            self::assertSame(409, $error->status);
        } finally {
            fclose($second);
        }
    }

    public function testUploadWithoutIdempotencyStoreSkipsIdempotency(): void
    {
        $storage = FileKit::storage($this->root);
        $uploads = FileKit::uploads($storage, $this->files); // idempotency = null
        $stream = FileKit::stream(self::PNG);
        $record = $uploads->upload($stream, null, 'a.png', FileVisibility::Private, null, 5, 'any-key');
        fclose($stream);
        self::assertGreaterThan(0, $record->id);
    }
}
