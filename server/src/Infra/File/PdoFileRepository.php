<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\File;

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
 * sand_system_file / sand_system_file_usage 的 PDO 实现。
 * 表结构只由 0001 基线迁移创建，本类不做任何 DDL；
 * 全部时间列按 timestamptz(0) 读写。
 */
final class PdoFileRepository implements FileRepository
{
    private const FILE_COLUMNS = 'f.id, f.original_name, f.kind, f.media_type, f.extension,'
        . ' f.size_bytes, f.sha256, f.storage_key, f.visibility, f.purpose,'
        . ' f.usage_tracked, f.uploader_id, f.create_time,'
        . ' usr.nickname AS uploader_name,'
        . ' (SELECT u.label FROM sand_system_file_usage u WHERE u.file_id = f.id'
        . '  ORDER BY u.create_time, u.subject_type, u.subject_id LIMIT 1) AS usage_label,'
        . ' EXISTS (SELECT 1 FROM sand_system_file_usage u2 WHERE u2.file_id = f.id) AS has_usage';

    private const FILE_FROM = ' FROM sand_system_file f'
        . ' LEFT JOIN sand_system_user usr ON usr.id = f.uploader_id';

    public function __construct(private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function insert(FileDraft $draft): int
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO sand_system_file ('
                . ' original_name, kind, media_type, extension, size_bytes, sha256,'
                . ' storage_key, visibility, purpose, usage_tracked, uploader_id'
                . ' ) VALUES ('
                . ' :original_name, :kind, :media_type, :extension, :size_bytes, :sha256,'
                . ' :storage_key, :visibility, :purpose, :usage_tracked, :uploader_id'
                . ' ) RETURNING id'
            );
            $statement->execute([
                'original_name' => $draft->originalName,
                'kind' => $draft->kind->value,
                'media_type' => $draft->mediaType,
                'extension' => $draft->extension,
                'size_bytes' => $draft->sizeBytes,
                'sha256' => $draft->sha256,
                'storage_key' => $draft->storageKey,
                'visibility' => $draft->visibility->value,
                'purpose' => $draft->purpose->value,
                'usage_tracked' => $draft->usageTracked ? 't' : 'f',
                'uploader_id' => $draft->uploaderId,
            ]);
            $id = $statement->fetchColumn();
        } catch (\Throwable $error) {
            throw FileException::internal('文件元数据写入失败', $error);
        }
        if (!is_numeric($id)) {
            throw FileException::internal('文件元数据写入失败');
        }

        return (int) $id;
    }

    public function findById(int $id): ?FileRecord
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT ' . self::FILE_COLUMNS . self::FILE_FROM
                . ' WHERE f.id = :id AND f.delete_time IS NULL'
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();
        } catch (\Throwable $error) {
            throw FileException::internal('文件元数据读取失败', $error);
        }
        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function search(FileQuery $query): FilePage
    {
        [$where, $params] = $this->where($query);
        try {
            $count = $this->pdo->prepare(
                'SELECT COUNT(*)' . self::FILE_FROM . ' WHERE ' . $where
            );
            $count->execute($params);
            $total = $count->fetchColumn();

            $page = max(1, $query->page);
            $pageSize = $query->pageSize;
            $statement = $this->pdo->prepare(
                'SELECT ' . self::FILE_COLUMNS . self::FILE_FROM
                . ' WHERE ' . $where
                . ' ORDER BY f.create_time DESC, f.id DESC'
                . ' LIMIT :limit OFFSET :offset'
            );
            $statement->bindValue('limit', $pageSize, \PDO::PARAM_INT);
            $statement->bindValue('offset', ($page - 1) * $pageSize, \PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                $statement->bindValue($key, $value);
            }
            $statement->execute();
            $rows = $statement->fetchAll();
        } catch (\Throwable $error) {
            throw FileException::internal('文件列表查询失败', $error);
        }
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw FileException::internal('文件列表查询失败');
            }
            $items[] = $this->hydrate($row);
        }
        if (!is_numeric($total)) {
            throw FileException::internal('文件列表查询失败');
        }

        return new FilePage($items, (int) $total, $page, $pageSize);
    }

    public function softDelete(int $id): bool
    {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE sand_system_file'
                . ' SET delete_time = CURRENT_TIMESTAMP, update_time = CURRENT_TIMESTAMP'
                . ' WHERE id = :id AND delete_time IS NULL'
            );
            $statement->execute(['id' => $id]);
        } catch (\Throwable $error) {
            throw FileException::internal('文件删除失败', $error);
        }

        return $statement->rowCount() > 0;
    }

    public function usagesFor(int $fileId): array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT file_id, subject_type, subject_id, label, create_time'
                . ' FROM sand_system_file_usage WHERE file_id = :file_id'
                . ' ORDER BY create_time, subject_type, subject_id'
            );
            $statement->execute(['file_id' => $fileId]);
            $rows = $statement->fetchAll();
        } catch (\Throwable $error) {
            throw FileException::internal('文件引用读取失败', $error);
        }
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw FileException::internal('文件引用读取失败');
            }
            $items[] = $this->hydrateUsage($row);
        }

        return $items;
    }

    public function addUsage(FileUsage $usage): void
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO sand_system_file_usage (file_id, subject_type, subject_id, label)'
                . ' VALUES (:file_id, :subject_type, :subject_id, :label)'
                . ' ON CONFLICT (file_id, subject_type, subject_id) DO NOTHING'
            );
            $statement->execute([
                'file_id' => $usage->fileId,
                'subject_type' => $usage->subjectType,
                'subject_id' => $usage->subjectId,
                'label' => $usage->label,
            ]);
        } catch (\Throwable $error) {
            throw FileException::internal('文件引用登记失败', $error);
        }
    }

    public function removeUsage(FileUsage $usage): void
    {
        try {
            $statement = $this->pdo->prepare(
                'DELETE FROM sand_system_file_usage'
                . ' WHERE file_id = :file_id AND subject_type = :subject_type AND subject_id = :subject_id'
            );
            $statement->execute([
                'file_id' => $usage->fileId,
                'subject_type' => $usage->subjectType,
                'subject_id' => $usage->subjectId,
            ]);
        } catch (\Throwable $error) {
            throw FileException::internal('文件引用取消失败', $error);
        }
    }

    public function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $error) {
            try {
                $this->pdo->rollBack();
            } catch (\Throwable) {
                // 回滚失败不掩盖原始异常
            }
            throw $error;
        }
    }

    /**
     * 附件管理列表只含 purpose=attachment（契约不提供 purpose 参数）；
     * usage_state 三态由 usage_tracked 与引用存在性推出。
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function where(FileQuery $query): array
    {
        $conditions = ['f.delete_time IS NULL', "f.purpose = 'attachment'"];
        $params = [];
        if ($query->keyword !== null && $query->keyword !== '') {
            $conditions[] = "f.original_name ILIKE :keyword ESCAPE '\\'";
            $params['keyword'] = '%' . $this->escapeLike($query->keyword) . '%';
        }
        if ($query->kind !== null) {
            $conditions[] = 'f.kind = :kind';
            $params['kind'] = $query->kind->value;
        }
        if ($query->usageState !== null) {
            $hasUsage = 'EXISTS (SELECT 1 FROM sand_system_file_usage u WHERE u.file_id = f.id)';
            $conditions[] = match ($query->usageState) {
                FileUsageState::Used => $hasUsage,
                FileUsageState::Unused => 'f.usage_tracked AND NOT ' . $hasUsage,
                FileUsageState::Unknown => 'NOT f.usage_tracked AND NOT ' . $hasUsage,
            };
        }
        if ($query->uploaderId !== null) {
            $conditions[] = 'f.uploader_id = :uploader_id';
            $params['uploader_id'] = $query->uploaderId;
        }
        if ($query->createTimeFrom !== null) {
            $conditions[] = 'f.create_time >= :create_time_from';
            $params['create_time_from'] = $query->createTimeFrom->format(\DateTimeInterface::ATOM);
        }
        if ($query->createTimeTo !== null) {
            $conditions[] = 'f.create_time <= :create_time_to';
            $params['create_time_to'] = $query->createTimeTo->format(\DateTimeInterface::ATOM);
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function hydrate(array $row): FileRecord
    {
        $id = $row['id'] ?? null;
        $name = $row['original_name'] ?? null;
        $kind = $row['kind'] ?? null;
        $mediaType = $row['media_type'] ?? null;
        $extension = $row['extension'] ?? null;
        $size = $row['size_bytes'] ?? null;
        $sha256 = $row['sha256'] ?? null;
        $storageKey = $row['storage_key'] ?? null;
        $visibility = $row['visibility'] ?? null;
        $purpose = $row['purpose'] ?? null;
        $usageTracked = $row['usage_tracked'] ?? null;
        $uploaderId = $row['uploader_id'] ?? null;
        $uploaderName = $row['uploader_name'] ?? null;
        $usageLabel = $row['usage_label'] ?? null;
        $hasUsage = $row['has_usage'] ?? null;
        $createTime = $row['create_time'] ?? null;
        if (!is_numeric($id) || !is_string($name) || !is_string($kind) || !is_string($mediaType)
            || !is_string($extension) || !is_numeric($size) || !is_string($sha256)
            || !is_string($storageKey) || !is_string($visibility) || !is_string($purpose)
            || !is_string($createTime)) {
            throw FileException::internal('文件元数据损坏');
        }
        try {
            return new FileRecord(
                id: (int) $id,
                originalName: $name,
                kind: FileKind::from($kind),
                mediaType: $mediaType,
                extension: $extension,
                sizeBytes: (int) $size,
                sha256: $sha256,
                storageKey: $storageKey,
                visibility: FileVisibility::from($visibility),
                purpose: FilePurpose::from($purpose),
                usageTracked: self::bool($usageTracked),
                uploaderId: is_numeric($uploaderId) ? (int) $uploaderId : null,
                uploaderName: is_string($uploaderName) ? $uploaderName : null,
                usageState: FileUsageState::resolve(self::bool($hasUsage), self::bool($usageTracked)),
                usageLabel: is_string($usageLabel) ? $usageLabel : null,
                createTime: new \DateTimeImmutable($createTime),
            );
        } catch (FileException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw FileException::internal('文件元数据损坏', $error);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function hydrateUsage(array $row): FileUsage
    {
        $fileId = $row['file_id'] ?? null;
        $subjectType = $row['subject_type'] ?? null;
        $subjectId = $row['subject_id'] ?? null;
        $label = $row['label'] ?? null;
        $createTime = $row['create_time'] ?? null;
        if (!is_numeric($fileId) || !is_string($subjectType) || !is_string($subjectId)
            || !is_string($label) || !is_string($createTime)) {
            throw FileException::internal('文件引用记录损坏');
        }
        try {
            return new FileUsage(
                (int) $fileId,
                $subjectType,
                $subjectId,
                $label,
                new \DateTimeImmutable($createTime),
            );
        } catch (FileException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw FileException::internal('文件引用记录损坏', $error);
        }
    }

    private static function bool(mixed $value): bool
    {
        return $value === true || $value === 't' || $value === '1' || $value === 1;
    }
}
