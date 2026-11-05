<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Job;

final class PdoJobStore implements JobStore
{
    private readonly string $driver;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $schemaPath = '',
    ) {
        $this->driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function ensureSchema(): void
    {
        $path = $this->schemaPath !== ''
            ? $this->schemaPath
            : dirname(__DIR__, 2) . '/database/migrations/2026_11_05_0001_create_job_run.sql';
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new JobStorageException('无法读取 job_run 迁移文件');
        }
        foreach (self::statements($sql) as $statement) {
            $this->pdo->exec($statement);
        }
    }

    public function insert(JobRun $job): void
    {
        $statement = $this->prepare(
            'INSERT INTO job_run (
                id, type, payload, status, stage, progress, result, error, locked_by,
                created_at, updated_at, started_at, finished_at
            ) VALUES (
                :id, :type, :payload, :status, :stage, :progress, :result, :error, :locked_by,
                :created_at, :updated_at, :started_at, :finished_at
            )'
        );
        $this->execute($statement, [
            'id' => $job->id,
            'type' => $job->type,
            'payload' => self::encode($job->payload),
            'status' => $job->status->value,
            'stage' => $job->stage,
            'progress' => $job->progress,
            'result' => $job->result === null ? null : self::encode($job->result),
            'error' => $job->error,
            'locked_by' => $job->lockedBy,
            'created_at' => $job->createdAt,
            'updated_at' => $job->updatedAt,
            'started_at' => $job->startedAt,
            'finished_at' => $job->finishedAt,
        ]);
    }

    public function find(string $id): ?JobRun
    {
        $statement = $this->prepare(
            'SELECT id, type, payload, status, stage, progress, result, error, locked_by,
                    created_at, updated_at, started_at, finished_at
             FROM job_run WHERE id = :id'
        );
        $this->execute($statement, ['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        if (!is_array($row)) {
            throw new JobStorageException('任务行格式不正确');
        }

        return $this->hydrate($row);
    }

    public function claimNext(string $workerId, string $now): ?JobRun
    {
        $started = false;
        try {
            $this->beginClaim();
            $started = true;
            $select = 'SELECT id FROM job_run WHERE status = :status ORDER BY created_at ASC, id ASC LIMIT 1';
            if ($this->driver === 'pgsql') {
                $select .= ' FOR UPDATE SKIP LOCKED';
            }
            $statement = $this->prepare($select);
            $this->execute($statement, ['status' => JobStatus::Pending->value]);
            $id = $statement->fetchColumn();
            if ($id === false) {
                $this->commitClaim();

                return null;
            }
            $update = $this->prepare(
                'UPDATE job_run
                 SET status = :running, stage = :stage, locked_by = :locked_by,
                     started_at = :started_at, updated_at = :updated_at
                 WHERE id = :id AND status = :pending'
            );
            $this->execute($update, [
                'running' => JobStatus::Running->value,
                'stage' => 'claimed',
                'locked_by' => $workerId,
                'started_at' => $now,
                'updated_at' => $now,
                'id' => (string) $id,
                'pending' => JobStatus::Pending->value,
            ]);
            if ($update->rowCount() !== 1) {
                $this->commitClaim();

                return null;
            }
            $job = $this->find((string) $id);
            $this->commitClaim();

            return $job;
        } catch (\Throwable $e) {
            if ($started) {
                try {
                    $this->rollBackClaim();
                } catch (\Throwable) {
                }
            }
            if ($e instanceof MissingJobSchemaException || $e instanceof JobStorageException) {
                throw $e;
            }
            throw new JobStorageException('认领任务失败', 0, $e);
        }
    }

    public function updateProgress(string $id, string $stage, int $progress, string $now): void
    {
        $statement = $this->prepare(
            'UPDATE job_run
             SET stage = :stage, progress = :progress, updated_at = :updated_at
             WHERE id = :id AND status = :status'
        );
        $this->execute($statement, [
            'stage' => $stage,
            'progress' => $progress,
            'updated_at' => $now,
            'id' => $id,
            'status' => JobStatus::Running->value,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new JobStorageException('无法更新任务进度');
        }
    }

    public function markSucceeded(string $id, ?array $result, string $now): void
    {
        $statement = $this->prepare(
            'UPDATE job_run
             SET status = :status, stage = :stage, progress = :progress,
                 result = :result, error = NULL, updated_at = :updated_at, finished_at = :finished_at
             WHERE id = :id AND status = :running'
        );
        $this->execute($statement, [
            'status' => JobStatus::Succeeded->value,
            'stage' => 'completed',
            'progress' => 100,
            'result' => $result === null ? null : self::encode($result),
            'updated_at' => $now,
            'finished_at' => $now,
            'id' => $id,
            'running' => JobStatus::Running->value,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new JobStorageException('无法将任务标记为成功');
        }
    }

    public function markFailed(string $id, string $error, string $now): void
    {
        $statement = $this->prepare(
            'UPDATE job_run
             SET status = :status, stage = :stage, error = :error,
                 updated_at = :updated_at, finished_at = :finished_at
             WHERE id = :id AND status = :running'
        );
        $this->execute($statement, [
            'status' => JobStatus::Failed->value,
            'stage' => 'failed',
            'error' => $error,
            'updated_at' => $now,
            'finished_at' => $now,
            'id' => $id,
            'running' => JobStatus::Running->value,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new JobStorageException('无法将任务标记为失败');
        }
    }

    /**
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $withoutComments = preg_replace('/--.*$/m', '', $sql);
        if (!is_string($withoutComments)) {
            throw new JobStorageException('无法解析 job_run 迁移文件');
        }
        $parts = explode(';', $withoutComments);
        $statements = [];
        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    private function prepare(string $sql): \PDOStatement
    {
        try {
            return $this->pdo->prepare($sql);
        } catch (\PDOException $e) {
            $this->translate($e);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function execute(\PDOStatement $statement, array $params): void
    {
        try {
            $statement->execute($params);
        } catch (\PDOException $e) {
            $this->translate($e);
        }
    }

    private function translate(\PDOException $e): never
    {
        $message = $e->getMessage();
        $code = (string) $e->getCode();
        if ($code === '42P01' || str_contains($message, 'no such table')) {
            throw new MissingJobSchemaException();
        }
        throw new JobStorageException('任务存储读写失败', 0, $e);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): JobRun
    {
        $status = JobStatus::tryFrom((string) ($row['status'] ?? ''));
        if ($status === null) {
            throw new JobStorageException('任务状态无法识别');
        }
        $stage = $row['stage'] ?? null;
        $error = $row['error'] ?? null;
        $started = $row['started_at'] ?? null;
        $finished = $row['finished_at'] ?? null;
        $locked = $row['locked_by'] ?? null;

        return new JobRun(
            id: (string) ($row['id'] ?? ''),
            type: (string) ($row['type'] ?? ''),
            status: $status,
            stage: is_string($stage) ? $stage : null,
            progress: (int) ($row['progress'] ?? 0),
            result: $this->decodeNullableArray($row['result'] ?? null),
            error: is_string($error) ? $error : null,
            payload: $this->decodePayload($row['payload'] ?? '{}'),
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? ''),
            startedAt: is_string($started) ? $started : null,
            finishedAt: is_string($finished) ? $finished : null,
            lockedBy: is_string($locked) ? $locked : null,
        );
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeNullableArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new JobStorageException('任务结果格式不正确');
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JobStorageException('任务结果无法解析', 0, $e);
        }
        if ($decoded === null) {
            return null;
        }
        if (!is_array($decoded)) {
            throw new JobStorageException('任务结果必须是 JSON 对象或数组');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $value): array
    {
        $decoded = $this->decodeNullableArray($value);
        if ($decoded === null) {
            return [];
        }
        $payload = [];
        foreach ($decoded as $key => $item) {
            if (!is_string($key)) {
                throw new JobStorageException('任务载荷必须是 JSON 对象');
            }
            $payload[$key] = $item;
        }

        return $payload;
    }

    /**
     * @param array<mixed> $value
     */
    private static function encode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new InvalidJobException('任务数据无法编码为 JSON', 0, $e);
        }
    }

    private function beginClaim(): void
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('BEGIN IMMEDIATE');

            return;
        }
        $this->pdo->beginTransaction();
    }

    private function commitClaim(): void
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('COMMIT');

            return;
        }
        $this->pdo->commit();
    }

    private function rollBackClaim(): void
    {
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('ROLLBACK');

            return;
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
