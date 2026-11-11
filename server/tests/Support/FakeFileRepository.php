<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Domain\File\FileDraft;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\FileKind;
use Sandadmin\Domain\File\FilePage;
use Sandadmin\Domain\File\FilePurpose;
use Sandadmin\Domain\File\FileQuery;
use Sandadmin\Domain\File\FileRecord;
use Sandadmin\Domain\File\FileRepository;
use Sandadmin\Domain\File\FileUsage;
use Sandadmin\Domain\File\FileUsageState;
use Sandadmin\Domain\File\FileVisibility;

/**
 * 内存版 FileRepository：单元测试用，不实现分页/过滤的完整语义，
 * 只保证附件管理面（purpose=attachment、未删除）的基本读写。
 */
final class FakeFileRepository implements FileRepository
{
    /** @var array<int, array{draft: FileDraft, deleted: bool}> */
    private array $files = [];

    /** @var array<int, list<FileUsage>> */
    private array $usages = [];

    private int $nextId = 1;

    public function insert(FileDraft $draft): int
    {
        $id = $this->nextId++;
        $this->files[$id] = ['draft' => $draft, 'deleted' => false];

        return $id;
    }

    public function findById(int $id): ?FileRecord
    {
        $entry = $this->files[$id] ?? null;
        if ($entry === null || $entry['deleted']) {
            return null;
        }

        return $this->record($id, $entry['draft']);
    }

    public function search(FileQuery $query): FilePage
    {
        $items = [];
        foreach ($this->files as $id => $entry) {
            if ($entry['deleted']) {
                continue;
            }
            $record = $this->record($id, $entry['draft']);
            if ($record->purpose !== FilePurpose::Attachment) {
                continue;
            }
            if ($query->keyword !== null && $query->keyword !== ''
                && !str_contains(mb_strtolower($record->originalName), mb_strtolower($query->keyword))) {
                continue;
            }
            if ($query->kind !== null && $record->kind !== $query->kind) {
                continue;
            }
            if ($query->uploaderId !== null && $record->uploaderId !== $query->uploaderId) {
                continue;
            }
            if ($query->usageState !== null && $record->usageState !== $query->usageState) {
                continue;
            }
            $items[] = $record;
        }
        $total = count($items);
        $offset = ($query->page - 1) * $query->pageSize;
        $page = array_slice($items, $offset, $query->pageSize);

        return new FilePage($page, $total, $query->page, $query->pageSize);
    }

    public function softDelete(int $id): bool
    {
        if (!isset($this->files[$id]) || $this->files[$id]['deleted']) {
            return false;
        }
        $this->files[$id]['deleted'] = true;

        return true;
    }

    public function usagesFor(int $fileId): array
    {
        return $this->usages[$fileId] ?? [];
    }

    public function addUsage(FileUsage $usage): void
    {
        $exists = false;
        foreach ($this->usages[$usage->fileId] ?? [] as $item) {
            if ($item->subjectType === $usage->subjectType && $item->subjectId === $usage->subjectId) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $this->usages[$usage->fileId][] = $usage;
        }
    }

    public function removeUsage(FileUsage $usage): void
    {
        $this->usages[$usage->fileId] = array_values(array_filter(
            $this->usages[$usage->fileId] ?? [],
            static fn (FileUsage $item): bool => !(
                $item->subjectType === $usage->subjectType && $item->subjectId === $usage->subjectId
            ),
        ));
    }

    public function transaction(callable $work): mixed
    {
        return $work();
    }

    private function record(int $id, FileDraft $draft): FileRecord
    {
        $usages = $this->usages[$id] ?? [];
        $hasUsage = $usages !== [];
        $usageLabel = $hasUsage ? $usages[0]->label : null;

        return new FileRecord(
            id: $id,
            originalName: $draft->originalName,
            kind: $draft->kind,
            mediaType: $draft->mediaType,
            extension: $draft->extension,
            sizeBytes: $draft->sizeBytes,
            sha256: $draft->sha256,
            storageKey: $draft->storageKey,
            visibility: $draft->visibility,
            purpose: $draft->purpose,
            usageTracked: $draft->usageTracked,
            uploaderId: $draft->uploaderId,
            uploaderName: null,
            usageState: FileUsageState::resolve($hasUsage, $draft->usageTracked),
            usageLabel: $usageLabel,
            createTime: new \DateTimeImmutable('2026-01-01 00:00:00+00:00'),
        );
    }
}
