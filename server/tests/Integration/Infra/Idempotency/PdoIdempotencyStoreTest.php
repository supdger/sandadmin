<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Infra\Idempotency;

use PHPUnit\Framework\Attributes\Group;
use Sandadmin\Domain\Idempotency\IdempotencyConflict;
use Sandadmin\Infra\Idempotency\PdoIdempotencyStore;
use Sandadmin\Tests\Integration\Support\PgFileCase;

/**
 * PdoIdempotencyStore 对 sand_system_idempotency_key 基线表的验证。
 * 需要 SAND_TEST_DB_URL。
 */
#[Group('integration')]
final class PdoIdempotencyStoreTest extends PgFileCase
{
    private function store(): PdoIdempotencyStore
    {
        return new PdoIdempotencyStore(self::pdo());
    }

    private function hash(string $seed): string
    {
        return hash('sha256', $seed);
    }

    public function testRecordAndFindActive(): void
    {
        $store = $this->store();
        self::assertNull($store->findActive(self::$userId, 'k1'));

        $store->record(self::$userId, 'k1', $this->hash('req-a'), ['id' => '1', 'name' => 'x']);
        $found = $store->findActive(self::$userId, 'k1');
        self::assertNotNull($found);
        self::assertSame($this->hash('req-a'), $found['request_hash']);
        // jsonb 解码为对象
        self::assertEquals((object) ['id' => '1', 'name' => 'x'], $found['response']);
    }

    public function testKeysAreScopedByUser(): void
    {
        $store = $this->store();
        $store->record(self::$userId, 'shared', $this->hash('a'), ['v' => 1]);
        self::assertNull($store->findActive(self::$otherUserId, 'shared'));
    }

    public function testDuplicateRecordConflicts(): void
    {
        $store = $this->store();
        $store->record(self::$userId, 'k2', $this->hash('a'), ['v' => 1]);
        $this->expectException(IdempotencyConflict::class);
        $store->record(self::$userId, 'k2', $this->hash('a'), ['v' => 1]);
    }

    public function testExpiredRecordIsReplaced(): void
    {
        $store = $this->store();
        $store->record(self::$userId, 'k3', $this->hash('old'), ['v' => 'old']);
        // 手工把时间拨到过去，模拟 24h 已过；ck 约束要求 expire_time > create_time，需一并回拨
        self::pdo()->exec(
            "UPDATE sand_system_idempotency_key"
            . " SET create_time = CURRENT_TIMESTAMP - INTERVAL '25 hours',"
            . " expire_time = CURRENT_TIMESTAMP - INTERVAL '1 hour'"
            . " WHERE user_id = " . self::$userId . " AND key = 'k3'"
        );
        self::assertNull($store->findActive(self::$userId, 'k3'));
        // 过期行已被清理，同键可重新记录
        $store->record(self::$userId, 'k3', $this->hash('new'), ['v' => 'new']);
        $found = $store->findActive(self::$userId, 'k3');
        self::assertSame($this->hash('new'), $found['request_hash'] ?? null);
    }

    public function testEmptyObjectResponseRoundtrip(): void
    {
        $store = $this->store();
        $store->record(self::$userId, 'k4', $this->hash('d'), new \stdClass());
        $found = $store->findActive(self::$userId, 'k4');
        self::assertNotNull($found);
        self::assertEquals(new \stdClass(), $found['response']);
    }
}
