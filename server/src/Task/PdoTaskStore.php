<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * sand_system_task_run 的 PDO 实现。表结构只由基线迁移创建，
 * 本类不做任何 DDL；所有时间列按 timestamptz(0) 读写 UTC。
 */
final class PdoTaskStore implements TaskStore
{
    private const TABLE = 'sand_system_task_run';

    private const COLUMNS = 'public_id, type, payload, state, stage, progress, result,'
        . ' error_code, error_message, created_by, idempotency_key, locked_by,'
        . ' lease_expire_time, attempt, start_time, finish_time, create_time, update_time';

    public function __construct(private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function insert(TaskRun $task): void
    {
        $statement = $this->prepare(
            'INSERT INTO ' . self::TABLE . ' (
                public_id, type, payload, state, stage, progress,
                created_by, idempotency_key, attempt,
                create_time, update_time
             ) VALUES (
                CAST(:public_id AS uuid), :type, CAST(:payload AS jsonb), :state, :stage, :progress,
                :created_by, :idempotency_key, :attempt,
                :create_time, :update_time
             )'
        );
        try {
            $statement->execute([
                'public_id' => $task->publicId,
                'type' => $task->type,
                'payload' => $this->encodeJson($task->payload),
                'state' => $task->state->value,
                'stage' => $task->stage,
                'progress' => $task->progress,
                'created_by' => $task->createdBy,
                'idempotency_key' => $task->idempotencyKey,
                'attempt' => $task->attempt,
                'create_time' => $this->time($task->createTime),
                'update_time' => $this->time($task->updateTime),
            ]);
        } catch (\PDOException $exception) {
            $this->throwTranslated($exception);
        }
    }

    public function findByPublicId(string $publicId): ?TaskRun
    {
        $statement = $this->prepare(
            'SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE
            . ' WHERE public_id = CAST(:public_id AS uuid)'
        );
        $statement->execute(['public_id' => $publicId]);
        $row = $this->row($statement);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByIdempotencyKey(string $type, string $idempotencyKey): ?TaskRun
    {
        $statement = $this->prepare(
            'SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE
            . ' WHERE type = :type AND idempotency_key = :key'
        );
        $statement->execute(['type' => $type, 'key' => $idempotencyKey]);
        $row = $this->row($statement);

        return $row === null ? null : $this->hydrate($row);
    }

    public function claimNext(string $workerId, \DateTimeImmutable $now, TaskClaimPolicy $policy): ?TaskRun
    {
        $this->pdo->beginTransaction();
        try {
            $claimed = $this->claimInTransaction($workerId, $now, $policy);
            $this->pdo->commit();

            return $claimed;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function claimInTransaction(string $workerId, \DateTimeImmutable $now, TaskClaimPolicy $policy): ?TaskRun
    {
        $select = $this->prepare(
            'SELECT public_id, type, state, attempt FROM ' . self::TABLE . '
             WHERE state = \'queued\'
                OR (state = \'running\' AND lease_expire_time IS NOT NULL AND lease_expire_time <= :now)
             ORDER BY create_time, id
             LIMIT 10
             FOR UPDATE SKIP LOCKED'
        );
        $select->execute(['now' => $this->time($now)]);
        $candidates = $this->rows($select);
        if ($candidates === []) {
            return null;
        }

        $exhausted = [];
        $types = [];
        foreach ($candidates as $candidate) {
            $publicId = self::text($candidate['public_id']);
            if (self::integer($candidate['attempt']) + 1 > $policy->maxAttempts) {
                $this->failExhausted($publicId, $now);
                $exhausted[$publicId] = true;
                continue;
            }
            $types[self::text($candidate['type'])] = true;
        }

        $runningCounts = $this->runningCountsByType(array_keys($types), $now);

        foreach ($candidates as $candidate) {
            $publicId = self::text($candidate['public_id']);
            if (isset($exhausted[$publicId])) {
                continue;
            }
            $type = self::text($candidate['type']);
            $limit = $policy->limitFor($type);
            $running = $runningCounts[$type] ?? 0;
            if ($limit > 0 && $running >= $limit) {
                continue;
            }

            $update = $this->prepare(
                'UPDATE ' . self::TABLE . '
                 SET state = \'running\', stage = \'执行中\',
                     locked_by = :worker, lease_expire_time = :lease,
                     attempt = attempt + 1,
                     start_time = COALESCE(start_time, :now),
                     update_time = :now2
                 WHERE public_id = CAST(:public_id AS uuid)
                   AND (state = \'queued\'
                        OR (state = \'running\' AND lease_expire_time IS NOT NULL AND lease_expire_time <= :now3))'
            );
            $leaseUntil = $now->modify(sprintf('+%d seconds', $policy->leaseSeconds));
            $update->execute([
                'worker' => $workerId,
                'lease' => $this->time($leaseUntil instanceof \DateTimeImmutable ? $leaseUntil : $now),
                'now' => $this->time($now),
                'now2' => $this->time($now),
                'public_id' => $publicId,
                'now3' => $this->time($now),
            ]);
            if ($update->rowCount() !== 1) {
                continue;
            }
            $runningCounts[$type] = ($runningCounts[$type] ?? 0) + 1;

            return $this->findByPublicId($publicId);
        }

        return null;
    }

    /**
     * @param list<string> $types
     * @return array<string, int>
     */
    private function runningCountsByType(array $types, \DateTimeImmutable $now): array
    {
        if ($types === []) {
            return [];
        }
        $placeholders = [];
        $params = ['now' => $this->time($now)];
        foreach ($types as $index => $type) {
            $name = 't' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $type;
        }
        // 只统计租约未过期的 running：租约已过期的视为可回收，不占并发名额，
        // 否则达到上限的陈旧任务永远无法被重新认领。
        $statement = $this->prepare(
            'SELECT type, COUNT(*) AS running_count FROM ' . self::TABLE . '
             WHERE state = \'running\'
               AND (lease_expire_time IS NULL OR lease_expire_time > :now)
               AND type IN (' . implode(', ', $placeholders) . ')
             GROUP BY type'
        );
        $statement->execute($params);
        $counts = [];
        foreach ($this->rows($statement) as $row) {
            $counts[self::text($row['type'])] = self::integer($row['running_count']);
        }

        return $counts;
    }

    private function failExhausted(string $publicId, \DateTimeImmutable $now): void
    {
        $statement = $this->prepare(
            'UPDATE ' . self::TABLE . '
             SET state = \'failed\', stage = \'执行失败\',
                 error_code = \'max_attempts_exceeded\',
                 error_message = \'任务多次执行未成功，已停止重试\',
                 finish_time = :now, lease_expire_time = NULL, update_time = :now2
             WHERE public_id = CAST(:public_id AS uuid)
               AND state IN (\'queued\', \'running\')'
        );
        $statement->execute([
            'now' => $this->time($now),
            'now2' => $this->time($now),
            'public_id' => $publicId,
        ]);
    }

    public function reportProgress(
        string $publicId,
        string $workerId,
        string $stage,
        int $progress,
        \DateTimeImmutable $now,
        int $leaseSeconds,
    ): void {
        $statement = $this->prepare(
            'UPDATE ' . self::TABLE . '
             SET stage = :stage, progress = :progress,
                 lease_expire_time = :lease, update_time = :now
             WHERE public_id = CAST(:public_id AS uuid)
               AND state = \'running\' AND locked_by = :worker'
        );
        $leaseUntil = $now->modify(sprintf('+%d seconds', $leaseSeconds));
        $statement->execute([
            'stage' => $stage,
            'progress' => $progress,
            'lease' => $this->time($leaseUntil instanceof \DateTimeImmutable ? $leaseUntil : $now),
            'now' => $this->time($now),
            'public_id' => $publicId,
            'worker' => $workerId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new TaskStorageException('任务执行租约已失效');
        }
    }

    public function markSucceeded(string $publicId, string $workerId, ?array $result, \DateTimeImmutable $now): void
    {
        $statement = $this->prepare(
            'UPDATE ' . self::TABLE . '
             SET state = \'succeeded\', stage = \'已完成\', progress = 100,
                 result = CAST(:result AS jsonb),
                 error_code = NULL, error_message = NULL,
                 finish_time = :now, lease_expire_time = NULL, update_time = :now2
             WHERE public_id = CAST(:public_id AS uuid)
               AND state = \'running\' AND locked_by = :worker'
        );
        $statement->execute([
            'result' => $result === null ? null : $this->encodeJson($result),
            'now' => $this->time($now),
            'now2' => $this->time($now),
            'public_id' => $publicId,
            'worker' => $workerId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new TaskStorageException('任务执行租约已失效');
        }
    }

    public function markFailed(
        string $publicId,
        string $workerId,
        string $errorCode,
        string $errorMessage,
        \DateTimeImmutable $now,
    ): void {
        $statement = $this->prepare(
            'UPDATE ' . self::TABLE . '
             SET state = \'failed\', stage = \'执行失败\',
                 error_code = :error_code, error_message = :error_message,
                 finish_time = :now, lease_expire_time = NULL, update_time = :now2
             WHERE public_id = CAST(:public_id AS uuid)
               AND state = \'running\' AND locked_by = :worker'
        );
        $statement->execute([
            'error_code' => mb_substr($errorCode, 0, 64),
            'error_message' => mb_substr($errorMessage, 0, 500),
            'now' => $this->time($now),
            'now2' => $this->time($now),
            'public_id' => $publicId,
            'worker' => $workerId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new TaskStorageException('任务执行租约已失效');
        }
    }

    public function search(?int $createdBy, ?string $type, ?string $state, int $page, int $pageSize): TaskPage
    {
        $wheres = [];
        $params = [];
        if ($createdBy !== null) {
            $wheres[] = 'created_by = :created_by';
            $params['created_by'] = $createdBy;
        }
        if ($type !== null && $type !== '') {
            $wheres[] = 'type = :type';
            $params['type'] = $type;
        }
        if ($state !== null && $state !== '') {
            $wheres[] = 'state = :state';
            $params['state'] = $state;
        }
        $where = $wheres === [] ? '' : 'WHERE ' . implode(' AND ', $wheres);

        $countStatement = $this->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' ' . $where);
        $countStatement->execute($params);
        $total = self::integer($countStatement->fetchColumn());

        $listStatement = $this->prepare(
            'SELECT ' . self::COLUMNS . ' FROM ' . self::TABLE . ' ' . $where
            . ' ORDER BY create_time DESC, id DESC LIMIT :size OFFSET :offset'
        );
        $listParams = $params + ['size' => $pageSize, 'offset' => ($page - 1) * $pageSize];
        $listStatement->execute($listParams);
        $items = [];
        foreach ($this->rows($listStatement) as $row) {
            $items[] = $this->hydrate($row);
        }

        return new TaskPage($items, $total, $page, $pageSize);
    }

    private function prepare(string $sql): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new TaskStorageException('任务存储准备语句失败');
        }

        return $statement;
    }

    /**
     * timestamptz(0) 统一按 UTC 写入；读回由 DateTimeImmutable 解析。
     */
    private function time(\DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeJson(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new TaskStorageException('任务数据不是合法 JSON', 0, $exception);
        }
    }

    /**
     * fetch(FETCH_ASSOC) 单行收敛为 array<string,mixed>。
     *
     * @return array<string, mixed>|null
     */
    private function row(\PDOStatement $statement): ?array
    {
        $raw = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($raw) ? $raw : null;
    }

    /**
     * fetchAll(FETCH_ASSOC) 的行统一收敛为 array<string,mixed>，
     * 非数组行视为存储异常。
     *
     * @return list<array<string, mixed>>
     */
    private function rows(\PDOStatement $statement): array
    {
        $rows = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new TaskStorageException('任务记录格式不正确');
            }
            $normalized = [];
            foreach ($row as $key => $value) {
                if (!is_string($key)) {
                    throw new TaskStorageException('任务记录字段名不正确');
                }
                $normalized[$key] = $value;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    private static function text(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new TaskStorageException('任务记录文本列不正确');
    }

    private static function nullableText(mixed $value): ?string
    {
        return $value === null ? null : self::text($value);
    }

    private static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new TaskStorageException('任务记录数值列不正确');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): TaskRun
    {
        try {
            $state = TaskState::from(self::text($row['state'] ?? null));
        } catch (\ValueError $exception) {
            throw new TaskStorageException('任务记录状态不正确', 0, $exception);
        }

        return new TaskRun(
            publicId: self::text($row['public_id'] ?? null),
            type: self::text($row['type'] ?? null),
            state: $state,
            stage: self::nullableText($row['stage'] ?? null),
            progress: self::integer($row['progress'] ?? null),
            result: $this->decodeJson($row['result'] ?? null),
            errorCode: self::nullableText($row['error_code'] ?? null),
            errorMessage: self::nullableText($row['error_message'] ?? null),
            payload: $this->decodeJson($row['payload'] ?? null) ?? [],
            createdBy: self::integer($row['created_by'] ?? null),
            idempotencyKey: self::nullableText($row['idempotency_key'] ?? null),
            attempt: self::integer($row['attempt'] ?? null),
            lockedBy: self::nullableText($row['locked_by'] ?? null),
            leaseExpireTime: $this->date($row['lease_expire_time'] ?? null),
            startTime: $this->date($row['start_time'] ?? null),
            finishTime: $this->date($row['finish_time'] ?? null),
            createTime: $this->dateRequired($row['create_time'] ?? null),
            updateTime: $this->dateRequired($row['update_time'] ?? null),
        );
    }

    /**
     * jsonb 经 PDO 返回字符串；NULL 与 JSON null 都按空结果处理。
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            return null;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TaskStorageException('任务记录 JSON 列损坏');
        }
        if ($decoded === null) {
            return null;
        }
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new TaskStorageException('任务记录 JSON 列不是对象');
        }
        $normalized = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new TaskStorageException('任务记录时间列不正确');
        }

        return new \DateTimeImmutable((string) $value);
    }

    private function dateRequired(mixed $value): \DateTimeImmutable
    {
        if ($value === null) {
            throw new TaskStorageException('任务记录时间列为空');
        }
        if (!is_string($value) && !is_int($value)) {
            throw new TaskStorageException('任务记录时间列不正确');
        }

        return new \DateTimeImmutable((string) $value);
    }

    private function throwTranslated(\PDOException $exception): never
    {
        $sqlState = (string) $exception->getCode();
        if ($sqlState === '23505') {
            if (str_contains($exception->getMessage(), 'uk_sand_system_task_run_idempotency')) {
                throw new TaskIdempotencyConflictException('相同幂等键的任务已存在', 0, $exception);
            }
            throw new TaskStorageException('任务记录唯一约束冲突', 0, $exception);
        }
        if ($sqlState === '42P01') {
            throw new TaskStorageException('任务表不存在，请先应用基线迁移', 0, $exception);
        }
        if ($sqlState === '23503') {
            throw new TaskStorageException('任务关联的用户不存在', 0, $exception);
        }

        throw new TaskStorageException('任务存储写入失败', 0, $exception);
    }
}
