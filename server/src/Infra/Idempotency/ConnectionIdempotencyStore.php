<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Idempotency;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Domain\Idempotency\IdempotencyStore;

/**
 * 用业务同一条 DBAL 连接写 sand_system_idempotency_key。
 * 调用方要在自己的事务里 record，重复键才能把业务插入一起回滚。
 * 有效期 24 小时，过期行在读取时删除。表只由基线迁移创建。
 */
final class ConnectionIdempotencyStore implements IdempotencyStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findActive(int $userId, string $key): ?array
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM sand_system_idempotency_key'
                . ' WHERE user_id = :user_id AND key = :key AND expire_time <= CURRENT_TIMESTAMP',
                ['user_id' => $userId, 'key' => $key],
            );
            $row = $this->connection->fetchAssociative(
                'SELECT request_hash, response FROM sand_system_idempotency_key'
                . ' WHERE user_id = :user_id AND key = :key',
                ['user_id' => $userId, 'key' => $key],
            );
        } catch (\Throwable $error) {
            throw new \RuntimeException('幂等记录读取失败', 0, $error);
        }
        if ($row === false) {
            return null;
        }
        $hash = $row['request_hash'] ?? null;
        if (!is_string($hash)) {
            throw new \RuntimeException('幂等记录损坏');
        }
        $response = null;
        $raw = $row['response'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, false);
            if ($decoded !== null || $raw === 'null') {
                $response = $decoded;
            }
        }

        return ['request_hash' => $hash, 'response' => $response];
    }

    public function record(int $userId, string $key, string $requestHash, mixed $response): void
    {
        try {
            $encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            throw new \RuntimeException('幂等结果序列化失败', 0, $error);
        }
        try {
            $this->connection->executeStatement(
                'INSERT INTO sand_system_idempotency_key ('
                . ' user_id, key, request_hash, response, expire_time'
                . ' ) VALUES ('
                . ' :user_id, :key, :request_hash, CAST(:response AS jsonb),'
                . " CURRENT_TIMESTAMP + INTERVAL '24 hours'"
                . ' )',
                [
                    'user_id' => $userId,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'response' => $encoded,
                ],
            );
        } catch (UniqueConstraintViolationException $error) {
            throw new IdempotencyConflict('幂等键已被占用', 0, $error);
        } catch (\Throwable $error) {
            throw new \RuntimeException('幂等记录写入失败', 0, $error);
        }
    }
}
