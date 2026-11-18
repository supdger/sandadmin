<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Stream;

use Sandadmin\Domain\Stream\BackpressurePolicy;
use Sandadmin\Domain\Stream\ProcessProfileInterface;

/**
 * 进程组形态的默认值实现：字段全部来自 config/stream.php，
 * 数值非法在装载期抛错而不是运行中悄悄生效。
 * 单流总时长钳在 300 秒硬顶内（J-5；预计更长的改后台任务）。
 */
final class StreamProfile implements ProcessProfileInterface
{
    public const STREAM_SECONDS_CAP = 300;

    public function __construct(
        private readonly string $role,
        private readonly int $streamLimit,
        private readonly int $idleSeconds,
        private readonly int $streamSeconds,
        private readonly int $heartbeatSeconds,
        private readonly int $probeSeconds,
        private readonly BackpressurePolicy $backpressure,
        private readonly int $maxRequests,
        private readonly int $memoryLimitBytes,
    ) {
        if ($role === '') {
            throw new \InvalidArgumentException('进程组角色不能为空');
        }
        if ($streamLimit < 1) {
            throw new \InvalidArgumentException('每进程挂路数上限至少为 1');
        }
        if ($idleSeconds < 1 || $heartbeatSeconds < 1 || $probeSeconds < 1) {
            throw new \InvalidArgumentException('心跳、权限复查、空闲超时都必须大于 0 秒');
        }
        if ($streamSeconds < 1 || $streamSeconds > self::STREAM_SECONDS_CAP) {
            throw new \InvalidArgumentException('单流总时长上限必须在 1–' . self::STREAM_SECONDS_CAP . ' 秒之间');
        }
        if ($maxRequests < 0 || $memoryLimitBytes < 0) {
            throw new \InvalidArgumentException('回收阈值为 0（不启用）或正数');
        }
    }

    /**
     * @param array<array-key, mixed> $config config/stream.php 里该角色的数组
     */
    public static function fromArray(string $role, array $config): self
    {
        return new self(
            role: $role,
            streamLimit: self::intValue($config, 'stream_limit', 80),
            idleSeconds: self::intValue($config, 'idle_seconds', 30),
            streamSeconds: self::intValue($config, 'stream_seconds', self::STREAM_SECONDS_CAP),
            heartbeatSeconds: self::intValue($config, 'heartbeat_seconds', 15),
            probeSeconds: self::intValue($config, 'probe_seconds', 30),
            backpressure: BackpressurePolicy::fromName(
                is_string($config['backpressure'] ?? null) ? $config['backpressure'] : 'pause',
            ),
            maxRequests: self::intValue($config, 'max_requests', 0),
            memoryLimitBytes: self::intValue($config, 'memory_limit_bytes', 0),
        );
    }

    public function role(): string
    {
        return $this->role;
    }

    public function streamLimit(): int
    {
        return $this->streamLimit;
    }

    public function idleSeconds(): int
    {
        return $this->idleSeconds;
    }

    public function streamSeconds(): int
    {
        return $this->streamSeconds;
    }

    public function heartbeatSeconds(): int
    {
        return $this->heartbeatSeconds;
    }

    public function probeSeconds(): int
    {
        return $this->probeSeconds;
    }

    public function backpressure(): BackpressurePolicy
    {
        return $this->backpressure;
    }

    public function maxRequests(): int
    {
        return $this->maxRequests;
    }

    public function memoryLimitBytes(): int
    {
        return $this->memoryLimitBytes;
    }

    /** @param array<array-key, mixed> $config */
    private static function intValue(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
