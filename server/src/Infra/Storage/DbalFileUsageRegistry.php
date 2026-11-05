<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Sandadmin\Storage\Exception\StorageException;
use Sandadmin\Storage\FileId;
use Sandadmin\Storage\FileUsageList;
use Sandadmin\Storage\FileUsageRecord;
use Sandadmin\Storage\FileUsageRef;
use Sandadmin\Storage\FileUsageRegistry;

final class DbalFileUsageRegistry implements FileUsageRegistry
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function register(FileUsageRef $ref): void
    {
        try {
            $this->connection->transactional(function () use ($ref): void {
                if ($this->find($ref) !== null) {
                    return;
                }
                $createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
                $this->connection->createQueryBuilder()
                    ->insert('file_usage')
                    ->values([
                        'file_id' => ':file_id',
                        'subject_type' => ':subject_type',
                        'subject_id' => ':subject_id',
                        'created_at' => ':created_at',
                    ])
                    ->setParameter('file_id', $ref->fileId)
                    ->setParameter('subject_type', $ref->subjectType)
                    ->setParameter('subject_id', $ref->subjectId)
                    ->setParameter('created_at', $createdAt->format('Y-m-d\TH:i:s.uP'))
                    ->executeStatement();
            });
        } catch (UniqueConstraintViolationException) {
            return;
        } catch (StorageException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new StorageException('storage_unavailable', '无法登记文件引用', [], $error);
        }
    }

    public function unregister(FileUsageRef $ref): void
    {
        try {
            $this->connection->createQueryBuilder()
                ->delete('file_usage')
                ->where('file_id = :file_id')
                ->andWhere('subject_type = :subject_type')
                ->andWhere('subject_id = :subject_id')
                ->setParameter('file_id', $ref->fileId)
                ->setParameter('subject_type', $ref->subjectType)
                ->setParameter('subject_id', $ref->subjectId)
                ->executeStatement();
        } catch (\Throwable $error) {
            throw new StorageException('storage_unavailable', '无法取消文件引用', [], $error);
        }
    }

    public function countFor(string $fileId): int
    {
        FileId::assert($fileId);
        try {
            $value = $this->connection->createQueryBuilder()
                ->select('COUNT(*)')
                ->from('file_usage')
                ->where('file_id = :file_id')
                ->setParameter('file_id', $fileId)
                ->executeQuery()
                ->fetchOne();
        } catch (\Throwable $error) {
            throw new StorageException('storage_unavailable', '无法读取文件引用', [], $error);
        }
        if (!is_int($value) && !is_string($value)) {
            throw new StorageException('storage_unavailable', '无法读取文件引用');
        }

        return (int) $value;
    }

    public function listFor(string $fileId): FileUsageList
    {
        FileId::assert($fileId);
        try {
            $rows = $this->connection->createQueryBuilder()
                ->select('file_id', 'subject_type', 'subject_id', 'created_at')
                ->from('file_usage')
                ->where('file_id = :file_id')
                ->setParameter('file_id', $fileId)
                ->orderBy('created_at', 'ASC')
                ->addOrderBy('subject_type', 'ASC')
                ->addOrderBy('subject_id', 'ASC')
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable $error) {
            throw new StorageException('storage_unavailable', '无法读取文件引用', [], $error);
        }
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrate($row);
        }

        return new FileUsageList($items);
    }

    private function find(FileUsageRef $ref): ?FileUsageRecord
    {
        $row = $this->connection->createQueryBuilder()
            ->select('file_id', 'subject_type', 'subject_id', 'created_at')
            ->from('file_usage')
            ->where('file_id = :file_id')
            ->andWhere('subject_type = :subject_type')
            ->andWhere('subject_id = :subject_id')
            ->setParameter('file_id', $ref->fileId)
            ->setParameter('subject_type', $ref->subjectType)
            ->setParameter('subject_id', $ref->subjectId)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): FileUsageRecord
    {
        $fileId = $row['file_id'] ?? null;
        $subjectType = $row['subject_type'] ?? null;
        $subjectId = $row['subject_id'] ?? null;
        $createdAt = $row['created_at'] ?? null;
        if (!is_string($fileId) || !is_string($subjectType) || !is_string($subjectId) || !is_string($createdAt)) {
            throw new StorageException('storage_unavailable', '文件引用记录损坏');
        }
        try {
            $at = new \DateTimeImmutable($createdAt);
        } catch (\Exception) {
            throw new StorageException('storage_unavailable', '文件引用记录损坏');
        }

        return new FileUsageRecord(new FileUsageRef($fileId, $subjectType, $subjectId), $at);
    }
}
