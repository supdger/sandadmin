<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support;

use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Domain\Idempotency\IdempotencyStore;

/** 内存版幂等存储：单元测试用，语义对齐 PdoIdempotencyStore。 */
final class FakeIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, array{hash: string, response: array<array-key, mixed>|\stdClass|string|int|float|bool|null}> */
    private array $rows = [];

    /** @return array{request_hash: string, response: array<array-key, mixed>|\stdClass|string|int|float|bool|null}|null */
    public function findActive(int $userId, string $key): ?array
    {
        $row = $this->rows[$userId . "\n" . $key] ?? null;
        if ($row === null) {
            return null;
        }

        return ['request_hash' => $row['hash'], 'response' => $row['response']];
    }

    public function record(int $userId, string $key, string $requestHash, mixed $response): void
    {
        $slot = $userId . "\n" . $key;
        if (isset($this->rows[$slot])) {
            throw new IdempotencyConflict('幂等键已被占用');
        }
        if (!is_array($response) && !$response instanceof \stdClass && !is_scalar($response) && $response !== null) {
            throw new \InvalidArgumentException('幂等快照必须是 JSON 值');
        }
        $this->rows[$slot] = ['hash' => $requestHash, 'response' => $response];
    }
}
