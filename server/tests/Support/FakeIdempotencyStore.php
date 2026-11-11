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
    /** @var array<string, array{hash: string, response: mixed}> */
    private array $rows = [];

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
        $this->rows[$slot] = ['hash' => $requestHash, 'response' => $response];
    }
}
