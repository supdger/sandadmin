<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * task.php 配置的类型化视图。数值非法时在装载期抛 InvalidTaskException，
 * 避免运行中拿到隐式默认值。
 */
final readonly class TaskConfig
{
    /**
     * @param array<string, int> $timeouts 各类型单次执行超时（秒），0 表示不限
     * @param array<string, int> $concurrency 各类型同时运行上限，0 表示不限
     */
    public function __construct(
        public float $pollInterval,
        public int $leaseSeconds,
        public int $maxAttempts,
        public int $timeoutSeconds,
        public array $timeouts,
        public array $concurrency,
        public int $defaultConcurrency,
        public int $maxBatch,
    ) {
    }

    /**
     * @param array<array-key, mixed> $config config/task.php 原始数组
     */
    public static function fromArray(array $config): self
    {
        return new self(
            pollInterval: self::floatValue($config, 'poll_interval', 1.0, 0.1, 60.0),
            leaseSeconds: self::intValue($config, 'lease_seconds', 60, 5, 3600),
            maxAttempts: self::intValue($config, 'max_attempts', 3, 1, 10),
            timeoutSeconds: self::intValue($config, 'timeout_seconds', 300, 0, 86400),
            timeouts: self::intMap($config, 'timeouts', 0, 86400),
            concurrency: self::intMap($config, 'concurrency', 0, 1000),
            defaultConcurrency: self::intValue($config, 'default_concurrency', 4, 0, 1000),
            maxBatch: self::intValue($config, 'max_batch', 10, 1, 100),
        );
    }

    public function claimPolicy(): TaskClaimPolicy
    {
        return new TaskClaimPolicy(
            leaseSeconds: $this->leaseSeconds,
            maxAttempts: $this->maxAttempts,
            typeLimits: $this->concurrency,
            defaultLimit: $this->defaultConcurrency,
        );
    }

    public function timeoutFor(string $type): int
    {
        return $this->timeouts[$type] ?? $this->timeoutSeconds;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function floatValue(array $config, string $key, float $default, float $min, float $max): float
    {
        $value = $config[$key] ?? $default;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidTaskException('任务配置不正确: ' . $key);
        }
        $number = (float) $value;
        if ($number < $min || $number > $max) {
            throw new InvalidTaskException('任务配置超出范围: ' . $key);
        }

        return $number;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function intValue(array $config, string $key, int $default, int $min, int $max): int
    {
        $value = $config[$key] ?? $default;
        if (!is_int($value)) {
            throw new InvalidTaskException('任务配置不正确: ' . $key);
        }
        if ($value < $min || $value > $max) {
            throw new InvalidTaskException('任务配置超出范围: ' . $key);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<string, int>
     */
    private static function intMap(array $config, string $key, int $min, int $max): array
    {
        $value = $config[$key] ?? [];
        if (!is_array($value)) {
            throw new InvalidTaskException('任务配置不正确: ' . $key);
        }
        $map = [];
        foreach ($value as $type => $seconds) {
            if (!is_string($type) || !is_int($seconds) || $seconds < $min || $seconds > $max) {
                throw new InvalidTaskException('任务配置不正确: ' . $key);
            }
            $map[$type] = $seconds;
        }

        return $map;
    }
}
