<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\File;

use PHPUnit\Framework\Attributes\Group;
use Sandadmin\Domain\File\FileDraft;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FilePage;
use Sandadmin\Domain\File\FilePurpose;
use Sandadmin\Domain\File\FileQuery;
use Sandadmin\Domain\File\FileUsage;
use Sandadmin\Domain\File\FileUsageState;
use Sandadmin\Domain\File\FileVisibility;
use Sandadmin\Infra\File\PdoFileRepository;
use Sandadmin\Tests\Integration\Support\PgFileCase;

/**
 * PdoFileRepository 对 sand_system_file / sand_system_file_usage 基线表的
 * 读写、过滤、软删与事务验证。需要 SAND_TEST_DB_URL。
 */
#[Group('integration')]
final class PdoFileRepositoryTest extends PgFileCase
{
    private function repository(): PdoFileRepository
    {
        return new PdoFileRepository(self::pdo());
    }

    /**
     * @param array{originalName?: string, kind?: FileKind, mediaType?: string, extension?: string,
     * sizeBytes?: int, sha256?: string, storageKey?: string, visibility?: FileVisibility,
     * purpose?: FilePurpose, usageTracked?: bool, uploaderId?: ?int} $overrides
     */
    private function draft(array $overrides = []): FileDraft
    {
        return new FileDraft(
            originalName: $overrides['originalName'] ?? '报告.pdf',
            kind: $overrides['kind'] ?? FileKind::Document,
            mediaType: $overrides['mediaType'] ?? 'application/pdf',
            extension: $overrides['extension'] ?? 'pdf',
            sizeBytes: $overrides['sizeBytes'] ?? 1024,
            sha256: $overrides['sha256'] ?? hash('sha256', random_bytes(8)),
            storageKey: $overrides['storageKey'] ?? bin2hex(random_bytes(16)),
            visibility: $overrides['visibility'] ?? FileVisibility::Private,
            purpose: $overrides['purpose'] ?? FilePurpose::Attachment,
            usageTracked: $overrides['usageTracked'] ?? false,
            uploaderId: $overrides['uploaderId'] ?? self::$userId,
        );
    }

    /**
     * @param array{keyword?: ?string, kind?: ?FileKind, usageState?: ?FileUsageState, uploaderId?: ?int,
     * createTimeFrom?: ?\DateTimeImmutable, createTimeTo?: ?\DateTimeImmutable, page?: int, pageSize?: int} $overrides
     */
    private function query(array $overrides = []): FileQuery
    {
        return new FileQuery(
            keyword: $overrides['keyword'] ?? null,
            kind: $overrides['kind'] ?? null,
            usageState: $overrides['usageState'] ?? null,
            uploaderId: $overrides['uploaderId'] ?? null,
            createTimeFrom: $overrides['createTimeFrom'] ?? null,
            createTimeTo: $overrides['createTimeTo'] ?? null,
            page: $overrides['page'] ?? 1,
            pageSize: $overrides['pageSize'] ?? 20,
        );
    }

    public function testInsertAndFindRoundtrip(): void
    {
        $repo = $this->repository();
        $id = $repo->insert($this->draft(['usageTracked' => true]));

        $record = $repo->findById($id);
        self::assertNotNull($record);
        self::assertSame('报告.pdf', $record->originalName);
        self::assertSame(FileKind::Document, $record->kind);
        self::assertSame('application/pdf', $record->mediaType);
        self::assertSame('pdf', $record->extension);
        self::assertSame(1024, $record->sizeBytes);
        self::assertSame(FileVisibility::Private, $record->visibility);
        self::assertSame(FilePurpose::Attachment, $record->purpose);
        self::assertTrue($record->usageTracked);
        self::assertSame(self::$userId, $record->uploaderId);
        self::assertNotNull($record->uploaderName);
        self::assertSame(FileUsageState::Unused, $record->usageState);
        self::assertNull($record->usageLabel);
        // FileObject 形状：id/uploader_id 为字符串
        $api = $record->toApi();
        self::assertSame((string) $id, $api['id']);
        self::assertSame((string) self::$userId, $api['uploader_id']);
        self::assertSame('unused', $api['usage_state']);
    }

    public function testFindByIdMissAndDeleted(): void
    {
        $repo = $this->repository();
        self::assertNull($repo->findById(999999));

        $id = $repo->insert($this->draft());
        self::assertTrue($repo->softDelete($id));
        self::assertNull($repo->findById($id));
        // 重复软删返回 false
        self::assertFalse($repo->softDelete($id));
    }

    public function testSearchFiltersAttachmentOnly(): void
    {
        $repo = $this->repository();
        $repo->insert($this->draft(['originalName' => '附件一.pdf']));
        $repo->insert($this->draft(['originalName' => '导出.csv', 'purpose' => FilePurpose::Export, 'kind' => FileKind::Sheet]));
        $repo->insert($this->draft(['originalName' => '插件.zip', 'purpose' => FilePurpose::Plugin, 'kind' => FileKind::Archive]));

        $page = $repo->search($this->query());
        self::assertSame(1, $page->total);
        self::assertSame('附件一.pdf', $page->items[0]->originalName);
    }

    public function testSearchKeywordAndKind(): void
    {
        $repo = $this->repository();
        $repo->insert($this->draft(['originalName' => '季度报表.xlsx', 'kind' => FileKind::Sheet, 'extension' => 'xlsx']));
        $repo->insert($this->draft(['originalName' => '说明.pdf']));

        $byKeyword = $repo->search($this->query(['keyword' => '报表']));
        self::assertSame(1, $byKeyword->total);
        self::assertSame('季度报表.xlsx', $byKeyword->items[0]->originalName);

        $byKind = $repo->search($this->query(['kind' => FileKind::Sheet]));
        self::assertSame(1, $byKind->total);

        $likeEscape = $repo->search($this->query(['keyword' => '100%']));
        self::assertSame(0, $likeEscape->total);
    }

    public function testSearchUsageStateAndUploader(): void
    {
        $repo = $this->repository();
        // used：tracked + 有引用；unused：tracked 无引用；unknown：未 tracked 无引用
        $used = $repo->insert($this->draft(['usageTracked' => true, 'uploaderId' => self::$userId]));
        $repo->insert($this->draft(['usageTracked' => true, 'uploaderId' => self::$otherUserId]));
        $repo->insert($this->draft(['usageTracked' => false]));
        $repo->addUsage(new FileUsage($used, 'announcement', '1', '公告《标题》'));

        self::assertSame(1, $repo->search($this->query(['usageState' => FileUsageState::Used]))->total);
        self::assertSame(1, $repo->search($this->query(['usageState' => FileUsageState::Unused]))->total);
        self::assertSame(1, $repo->search($this->query(['usageState' => FileUsageState::Unknown]))->total);
        // uploader_id=self::$userId 命中 used 与 unknown 两条
        self::assertSame(2, $repo->search($this->query(['uploaderId' => self::$userId]))->total);

        $page = $repo->search($this->query(['uploaderId' => self::$otherUserId]));
        self::assertSame(1, $page->total);
        self::assertSame(self::$otherUserId, $page->items[0]->uploaderId);
    }

    public function testUsageCrudAndCascade(): void
    {
        $repo = $this->repository();
        $id = $repo->insert($this->draft(['usageTracked' => true]));
        $usage = new FileUsage($id, 'article', 'abc:123', '文章《测试》');

        $repo->addUsage($usage);
        // 幂等：同主键重复登记不产生第二行
        $repo->addUsage($usage);
        $usages = $repo->usagesFor($id);
        self::assertCount(1, $usages);
        self::assertSame('article', $usages[0]->subjectType);
        self::assertSame('abc:123', $usages[0]->subjectId);
        self::assertSame('文章《测试》', $usages[0]->label);

        // 引用存在 → used，usage_label 取出
        $record = $repo->findById($id);
        self::assertNotNull($record);
        self::assertSame(FileUsageState::Used, $record->usageState);
        self::assertSame('文章《测试》', $record->usageLabel);

        $repo->removeUsage($usage);
        self::assertSame([], $repo->usagesFor($id));
        // usage_tracked=true 且无引用 → unused
        $record = $repo->findById($id);
        self::assertNotNull($record);
        self::assertSame(FileUsageState::Unused, $record->usageState);
        self::assertNull($record->usageLabel);
    }

    public function testUsageCascadeOnHardDelete(): void
    {
        $repo = $this->repository();
        $id = $repo->insert($this->draft());
        $repo->addUsage(new FileUsage($id, 'x', '1', 'l'));
        // 物理删除（清理任务路径）级联清掉 usage 行
        self::pdo()->exec('DELETE FROM sand_system_file WHERE id = ' . $id);
        self::assertSame([], $repo->usagesFor($id));
    }

    public function testTransactionRollback(): void
    {
        $repo = $this->repository();
        $inserted = false;
        try {
            $repo->transaction(function () use ($repo, &$inserted): void {
                $repo->insert($this->draft());
                $inserted = true;
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
        self::assertTrue($inserted);
        self::assertSame(0, $repo->search($this->query())->total);
    }

    public function testInsertRejectsBadSha256ViaCheck(): void
    {
        $repo = $this->repository();
        // sha256 非 64 位十六进制 → CHECK 约束失败 → FileException 500
        $this->expectException(FileException::class);
        $repo->insert($this->draft(['sha256' => 'not-a-hash']));
    }

    public function testCreateTimeFilters(): void
    {
        $repo = $this->repository();
        $old = $repo->insert($this->draft());
        self::pdo()->exec(
            "UPDATE sand_system_file SET create_time = '2020-01-01 00:00:00+00' WHERE id = " . $old
        );
        $repo->insert($this->draft());

        $from = new \DateTimeImmutable('2021-01-01T00:00:00+00:00');
        $page = $repo->search($this->query(['createTimeFrom' => $from]));
        self::assertSame(1, $page->total);

        $to = new \DateTimeImmutable('2021-01-01T00:00:00+00:00');
        $page = $repo->search($this->query(['createTimeTo' => $to]));
        self::assertSame(1, $page->total);
    }
}
