<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Idempotency;

use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Domain\Idempotency\IdempotencyStore;

/**
 * sand_system_idempotency_key 的 PDO 实现（api-conventions §8）。
 * 表结构只由 0001 基线迁移创建，本类不做任何 DDL；
 * 有效期固定 24 小时（expire_time = create_time + 24h），过期行就地删除。
 * 存储故障抛 RuntimeException，由上层统一转 500，不属于业务错误。
 */
final class PdoIdempotencyStore implements IdempotencyStore
{
    private const USER_KEY_INDEX = 'uk_sand_system_idempotency_key_user_key';

    public function __construct(private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function findActive(int $userId, string $key): ?array
    {
        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM sand_system_idempotency_key'
                . ' WHERE user_id = :user_id AND key = :key AND expire_time <= CURRENT_TIMESTAMP'
            );
            $delete->execute(['user_id' => $userId, 'key' => $key]);

            $statement = $this->pdo->prepare(
                'SELECT request_hash, response FROM sand_system_idempotency_key'
                . ' WHERE user_id = :user_id AND key = :key'
            );
            $statement->execute(['user_id' => $userId, 'key' => $key]);
            $row = $statement->fetch();
        } catch (\Throwable $error) {
            throw new \RuntimeException('幂等记录读取失败', 0, $error);
        }
        if (!is_array($row)) {
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
            $statement = $this->pdo->prepare(
                'INSERT INTO sand_system_idempotency_key ('
                . ' user_id, key, request_hash, response, expire_time'
                . ' ) VALUES ('
                . ' :user_id, :key, :request_hash, CAST(:response AS jsonb),'
                . " CURRENT_TIMESTAMP + INTERVAL '24 hours'"
                . ' )'
            );
            $statement->execute([
                'user_id' => $userId,
                'key' => $key,
                'request_hash' => $requestHash,
                'response' => $encoded,
            ]);
        } catch (\PDOException $error) {
            if (str_contains($error->getMessage(), self::USER_KEY_INDEX)) {
                throw new IdempotencyConflict('幂等键已被占用', 0, $error);
            }
            throw new \RuntimeException('幂等记录写入失败', 0, $error);
        } catch (\Throwable $error) {
            throw new \RuntimeException('幂等记录写入失败', 0, $error);
        }
    }
}
