<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Idempotency;

/**
 * 写接口幂等记录端口（sand_system_idempotency_key，api-conventions §8）。
 * 同键同请求 24 小时内重放首次结果；同键不同内容返回 409。
 * record 必须与业务写入在同一事务内调用，保证结果与幂等记录原子提交；
 * 统一请求上下文/幂等中间件（B2）落地后由其实现替换本端口的使用方。
 */
interface IdempotencyStore
{
    /**
     * 查询有效期内记录；过期记录顺手删除并视为不存在。
     *
     * @return array{request_hash: string, response: mixed}|null
     *         response 为首次响应 data 快照（jsonb 解码值），无记录返回 null
     */
    public function findActive(int $userId, string $key): ?array;

    /**
     * 写入首次结果快照。已存在有效记录时抛 IdempotencyConflict，
     * 调用方应回滚业务事务并按既有记录重放或报 409。
     *
     * @param mixed $response 首次响应 data（jsonb 可序列化值）
     */
    public function record(int $userId, string $key, string $requestHash, mixed $response): void;
}
