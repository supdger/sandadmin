<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

use Doctrine\DBAL\Connection;
use Sandadmin\Infra\Migration\Exception\MigrationBusyException;

/**
 * 迁移锁：PostgreSQL 会话级 advisory lock，按连接（库）隔离。
 * 拿不到锁立即报错退出，不排队等待。
 */
final class MigrationLock
{
    private readonly int $key;

    public function __construct(private readonly Connection $conn)
    {
        // 60 位以内的正数键，保证落在 bigint 正数域
        $this->key = (int) hexdec(substr(hash('sha256', 'sandadmin:migration-lock'), 0, 15));
    }

    public function key(): int
    {
        return $this->key;
    }

    /**
     * @throws MigrationBusyException 锁被其他会话持有时
     */
    public function acquire(): void
    {
        $got = $this->conn->executeQuery(
            'SELECT pg_try_advisory_lock(:key)',
            ['key' => $this->key],
        )->fetchOne();
        if ($got !== true && $got !== 't' && $got !== 1 && $got !== '1') {
            throw new MigrationBusyException(
                '迁移锁被另一执行者持有，按约定不排队、直接退出。'
                . '确认没有并发迁移在跑后重试；残留锁可用 pg_advisory_unlock 人工释放。'
            );
        }
    }

    public function release(): void
    {
        $this->conn->executeStatement(
            'SELECT pg_advisory_unlock(:key)',
            ['key' => $this->key],
        );
    }
}
