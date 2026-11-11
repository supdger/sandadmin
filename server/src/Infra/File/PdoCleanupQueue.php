<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\File;

use Ramsey\Uuid\Uuid;
use Sandadmin\Application\File\CleanupQueue;
use Sandadmin\Domain\File\FileException;

/**
 * 清理任务的受理实现：向 sand_system_task_run 投递 type=cleanup.files 的
 * queued 记录；同 (type, idempotency_key) 重复投递返回既有任务。
 * TODO(#57): task_run 子系统（TaskDispatcher/PdoTaskStore）合入后，
 * 本类应删除并改调其投递入口，避免两套写路径。
 */
final class PdoCleanupQueue implements CleanupQueue
{
    private const TASK_TYPE = 'cleanup.files';

    public function __construct(private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function enqueue(int $createdBy, ?string $idempotencyKey): array
    {
        if ($createdBy < 1) {
            throw FileException::internal('任务发起人不正确');
        }
        if ($idempotencyKey !== null) {
            $existing = $this->findByKey($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }
        $taskId = Uuid::uuid4()->toString();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:sP');
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO sand_system_task_run ('
                . ' public_id, type, payload, state, stage, progress,'
                . ' created_by, idempotency_key, attempt, create_time, update_time'
                . ' ) VALUES ('
                . ' CAST(:public_id AS uuid), :type, \'{}\'::jsonb, \'queued\', \'排队中\', 0,'
                . ' :created_by, :idempotency_key, 0, :create_time, :update_time'
                . ' )'
            );
            $statement->execute([
                'public_id' => $taskId,
                'type' => self::TASK_TYPE,
                'created_by' => $createdBy,
                'idempotency_key' => $idempotencyKey,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        } catch (\PDOException $error) {
            // 幂等键并发冲突：以先落库的记录为准
            if ($idempotencyKey !== null && str_contains($error->getMessage(), 'uk_sand_system_task_run_idempotency')) {
                $existing = $this->findByKey($idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw FileException::internal('清理任务创建失败', $error);
        } catch (\Throwable $error) {
            throw FileException::internal('清理任务创建失败', $error);
        }

        return ['task_id' => $taskId, 'state' => 'queued'];
    }

    /** @return array{task_id: string, state: string}|null */
    private function findByKey(string $idempotencyKey): ?array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT public_id::text AS public_id, state FROM sand_system_task_run'
                . ' WHERE type = :type AND idempotency_key = :key'
            );
            $statement->execute(['type' => self::TASK_TYPE, 'key' => $idempotencyKey]);
            $row = $statement->fetch();
        } catch (\Throwable $error) {
            throw FileException::internal('清理任务查询失败', $error);
        }
        if (!is_array($row)) {
            return null;
        }
        $publicId = $row['public_id'] ?? null;
        $state = $row['state'] ?? null;
        if (!is_string($publicId) || !is_string($state)) {
            return null;
        }

        return ['task_id' => $publicId, 'state' => $state];
    }
}
