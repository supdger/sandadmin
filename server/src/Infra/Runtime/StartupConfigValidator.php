<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Runtime;

/**
 * 启动期配置 schema 校验（3i §5 / M-25）：全部 config/*.php 的关键键
 * 在 worker 引导时过一遍，不合法直接抛异常拒绝启动，不做运行中热改。
 * 校验只管形状与取值域，不重复各领域 Guard 已覆盖的业务规则
 * （弱密码表、日志留存下限、发号器号段等仍归各自 Guard）。
 */
final class StartupConfigValidator
{
    /**
     * @param array<mixed> $config 已加载的配置树（config() 分组取值）
     *
     * @throws \InvalidArgumentException 任一键形状或取值非法，消息可定位到 config/文件.键
     */
    public static function assertValid(array $config): void
    {
        self::assertRuntime($config['runtime'] ?? null);
        self::assertStream($config['stream'] ?? null);
        self::assertServer($config['server'] ?? null);
        self::assertTask($config['task'] ?? null);
        self::assertProcess($config['process'] ?? null);
    }

    /** @param mixed $runtime */
    private static function assertRuntime(mixed $runtime): void
    {
        $runtime = self::asOptionalArray($runtime, 'runtime');
        if ($runtime === null) {
            return;
        }
        $watermark = $runtime['memory_watermark_bytes'] ?? null;
        if ($watermark !== null && (!is_int($watermark) || $watermark < 0)) {
            throw new \InvalidArgumentException('runtime.memory_watermark_bytes 必须是 null（自动推导）或不小于 0 的整数');
        }
    }

    /** @param mixed $stream */
    private static function assertStream(mixed $stream): void
    {
        $stream = self::asOptionalArray($stream, 'stream');
        if ($stream === null) {
            return;
        }
        $profiles = self::asRequiredArray($stream['profiles'] ?? null, 'stream.profiles');
        foreach ($profiles as $role => $rawProfile) {
            $roleName = (string) $role;
            $profile = self::asRequiredArray($rawProfile, 'stream.profiles.' . $roleName);
            foreach (['stream_limit', 'idle_seconds', 'stream_seconds', 'heartbeat_seconds', 'probe_seconds', 'max_requests', 'memory_limit_bytes'] as $key) {
                $value = $profile[$key] ?? null;
                if ($value === null) {
                    continue;
                }
                if (!is_int($value)) {
                    throw new \InvalidArgumentException(sprintf('stream.profiles.%s.%s 必须是整数', $roleName, $key));
                }
                if ($value < 0) {
                    throw new \InvalidArgumentException(sprintf('stream.profiles.%s.%s 不能为负数', $roleName, $key));
                }
            }
            $backpressure = $profile['backpressure'] ?? null;
            if ($backpressure !== null && !in_array($backpressure, ['pause', 'drop'], true)) {
                throw new \InvalidArgumentException(sprintf('stream.profiles.%s.backpressure 取值非法', $roleName));
            }
        }
    }

    /** @param mixed $server */
    private static function assertServer(mixed $server): void
    {
        $server = self::asOptionalArray($server, 'server');
        if ($server === null) {
            return;
        }
        foreach (['stop_timeout', 'max_package_size'] as $key) {
            $value = $server[$key] ?? null;
            if ($value === null) {
                continue;
            }
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException(sprintf('server.%s 必须是正整数', $key));
            }
        }
        foreach (['listen', 'pid_file', 'status_file', 'stdout_file', 'log_file'] as $key) {
            $value = $server[$key] ?? null;
            if ($value !== null && !is_string($value)) {
                throw new \InvalidArgumentException(sprintf('server.%s 必须是字符串', $key));
            }
        }
    }

    /** @param mixed $task */
    private static function assertTask(mixed $task): void
    {
        $task = self::asOptionalArray($task, 'task');
        if ($task === null) {
            return;
        }
        // poll_interval 允许小数秒（config/task.php 现值 1.0），其余键只收整数
        $interval = $task['poll_interval'] ?? null;
        if ($interval !== null && !(is_int($interval) || is_float($interval)) || (is_numeric($interval) && $interval <= 0)) {
            throw new \InvalidArgumentException('task.poll_interval 必须是正数');
        }
        foreach (['lease_seconds', 'max_attempts', 'max_batch', 'default_concurrency'] as $key) {
            $value = $task[$key] ?? null;
            if ($value === null) {
                continue;
            }
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException(sprintf('task.%s 必须是正整数', $key));
            }
        }
        $timeout = $task['timeout_seconds'] ?? null;
        if ($timeout !== null && (!is_int($timeout) || $timeout <= 0)) {
            throw new \InvalidArgumentException('task.timeout_seconds 必须是正整数');
        }
        foreach (['timeouts', 'concurrency'] as $key) {
            $value = $task[$key] ?? null;
            if ($value !== null && !is_array($value)) {
                throw new \InvalidArgumentException(sprintf('task.%s 必须是数组', $key));
            }
        }
    }

    /** @param mixed $process */
    private static function assertProcess(mixed $process): void
    {
        $process = self::asOptionalArray($process, 'process');
        if ($process === null) {
            return;
        }
        foreach ($process as $name => $entry) {
            $entryName = (string) $name;
            $entry = self::asRequiredArray($entry, 'process.' . $entryName);
            $count = $entry['count'] ?? null;
            if ($count !== null && (!is_int($count) || $count < 1)) {
                throw new \InvalidArgumentException(sprintf('process.%s.count 必须是不小于 1 的整数', $entryName));
            }
            $handler = $entry['handler'] ?? null;
            if ($handler !== null && !is_string($handler)) {
                throw new \InvalidArgumentException(sprintf('process.%s.handler 必须是字符串', $entryName));
            }
        }
    }

    /**
     * 形状收窄：返回值类型即「已确认是数组」，下游 offset 访问不再是 mixed 报错源。
     *
     * @return array<mixed>|null
     */
    private static function asOptionalArray(mixed $value, string $key): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException($key . ' 必须是数组');
        }

        return $value;
    }

    /** @return array<mixed> */
    private static function asRequiredArray(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($key . ' 必须是数组');
        }

        return $value;
    }
}
