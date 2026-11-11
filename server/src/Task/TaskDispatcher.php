<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 投递任务：校验载荷保密约束后落库为 queued 记录。
 * 同一 (type, idempotency_key) 重复投递直接返回既有记录，不产生第二条。
 */
final class TaskDispatcher
{
    /**
     * payload/result 中禁止出现的键名（大小写不敏感、下划线归一化后比较）。
     * 只拦截明显凭据字段，不做通用敏感词过滤。
     */
    private const FORBIDDEN_KEYS = [
        'password', 'passwd', 'pwd', 'secret', 'token', 'apikey', 'api_key',
        'appsecret', 'app_secret', 'privatekey', 'private_key', 'credential',
        'credentials', 'authorization', 'cookie', 'session',
    ];

    public function __construct(
        private readonly TaskStore $store,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $type, array $payload, int $createdBy, ?string $idempotencyKey = null): TaskRun
    {
        if (preg_match('/^[a-z][a-z0-9_.]{0,63}$/', $type) !== 1) {
            throw new InvalidTaskException('任务类型标识不正确');
        }
        if ($createdBy < 1) {
            throw new InvalidTaskException('任务发起人不正确');
        }
        $this->assertNoSecrets($payload, 'payload');
        if ($idempotencyKey !== null) {
            if (preg_match('/^[A-Za-z0-9._~-]{1,128}$/', $idempotencyKey) !== 1) {
                throw new InvalidTaskException('幂等键不正确');
            }
            $existing = $this->store->findByIdempotencyKey($type, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        $now = $this->clock->now();
        $task = new TaskRun(
            publicId: $this->uuid(),
            type: $type,
            state: TaskState::Queued,
            stage: '排队中',
            progress: 0,
            result: null,
            errorCode: null,
            errorMessage: null,
            payload: $payload,
            createdBy: $createdBy,
            idempotencyKey: $idempotencyKey,
            attempt: 0,
            lockedBy: null,
            leaseExpireTime: null,
            startTime: null,
            finishTime: null,
            createTime: $now,
            updateTime: $now,
        );
        try {
            $this->store->insert($task);
        } catch (TaskIdempotencyConflictException) {
            // 并发投递同键：以先落库的记录为准
            $existing = $idempotencyKey === null
                ? null
                : $this->store->findByIdempotencyKey($type, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
            throw new TaskStorageException('任务写入冲突且无法读取既有记录');
        }

        return $task;
    }

    /**
     * 递归检查 JSON 载荷里是否混入凭据类字段；命中即拒绝，
     * 避免密钥随任务记录落库或进日志。
     *
     * @param array<array-key, mixed> $payload
     */
    private function assertNoSecrets(array $payload, string $path): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $compact = preg_replace('/[^a-z0-9]/i', '', $key);
                $normalized = is_string($compact) ? strtolower($compact) : '';
                if (in_array($normalized, self::FORBIDDEN_KEYS, true)) {
                    throw new InvalidTaskException('任务载荷包含不允许的敏感字段: ' . $path . '.' . $key);
                }
            }
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $this->assertNoSecrets($item, $path . '.' . $key . '[' . $index . ']');
                        }
                    }
                } else {
                    $this->assertNoSecrets($value, $path . (is_string($key) ? '.' . $key : ''));
                }
            }
        }
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}
