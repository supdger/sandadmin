<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

/**
 * 日志留存策略：解析 config('log') 各通道的 retention 配置并校验下限。
 * 安全类（security）下限 180 天、普通类（general）下限 30 天，缺省按 general、
 * 180 天——网络安全法要求网络日志留存不少于六个月是下限依据（T-4/N9）。
 * 文件日志清理（LogFileCleaner/LogRetentionWorker）与将来的库表日志
 * （审计、登录日志表）清理统一从本类取留存天数。
 *
 * @phpstan-type ChannelRetention array{name:string,kind:string,days:int,files:list<string>}
 */
final class LogRetentionPolicy
{
    public const KIND_SECURITY = 'security';
    public const KIND_GENERAL = 'general';
    public const MIN_SECURITY_DAYS = 180;
    public const MIN_GENERAL_DAYS = 30;
    public const DEFAULT_DAYS = 180;

    /** @var array<string, ChannelRetention> */
    private readonly array $channels;

    /** @param array<string, ChannelRetention> $channels */
    private function __construct(array $channels)
    {
        $this->channels = $channels;
    }

    /** @param array<mixed> $logConfig config('log') 原文；非字符串键与非数组通道项跳过 */
    public static function fromArray(array $logConfig): self
    {
        $channels = [];
        foreach ($logConfig as $name => $channel) {
            if (!is_string($name) || !is_array($channel)) {
                continue;
            }
            $retention = $channel['retention'] ?? [];
            if (!is_array($retention)) {
                $retention = [];
            }
            $kind = $retention['kind'] ?? self::KIND_GENERAL;
            $days = $retention['days'] ?? self::DEFAULT_DAYS;
            $channels[$name] = [
                'name' => $name,
                'kind' => is_string($kind) ? $kind : '',
                'days' => is_numeric($days) ? (int) $days : 0,
                'files' => self::handlerFiles($channel),
            ];
        }

        return new self($channels);
    }

    /**
     * 任一通道配置非法或 days 低于所属类别下限时抛 LogRetentionException，
     * 错误信息写明通道、配置值与下限，由启动守卫转成拒绝启动。
     */
    public function assertValid(): void
    {
        $errors = [];
        foreach ($this->channels as $channel) {
            $min = self::minDays($channel['kind']);
            if ($min === null) {
                $errors[] = sprintf(
                    '日志通道 %s 的 retention.kind="%s" 非法（只接受 %s/%s）',
                    $channel['name'],
                    $channel['kind'],
                    self::KIND_SECURITY,
                    self::KIND_GENERAL,
                );
                continue;
            }
            if ($channel['days'] < $min) {
                $errors[] = sprintf(
                    '日志通道 %s 留存 %d 天低于 %s 类下限 %d 天',
                    $channel['name'],
                    $channel['days'],
                    $channel['kind'],
                    $min,
                );
            }
        }
        if ($errors !== []) {
            throw new LogRetentionException(implode('；', $errors));
        }
    }

    /** @return list<ChannelRetention> */
    public function listChannels(): array
    {
        return array_values($this->channels);
    }

    /** 通道留存天数；库表日志清理等后续入口统一取这里，通道未配置即报错。 */
    public function resolveDays(string $channel): int
    {
        if (!isset($this->channels[$channel])) {
            throw new LogRetentionException(sprintf('日志通道 %s 未配置', $channel));
        }

        return $this->channels[$channel]['days'];
    }

    /** @return list<array{channel:string,days:int,path:string}> 文件日志清理目标（通道 × 配置的日志文件） */
    public function listFileTargets(): array
    {
        $targets = [];
        foreach ($this->channels as $channel) {
            foreach ($channel['files'] as $file) {
                $targets[] = [
                    'channel' => $channel['name'],
                    'days' => $channel['days'],
                    'path' => $file,
                ];
            }
        }

        return $targets;
    }

    private static function minDays(string $kind): ?int
    {
        return match ($kind) {
            self::KIND_SECURITY => self::MIN_SECURITY_DAYS,
            self::KIND_GENERAL => self::MIN_GENERAL_DAYS,
            default => null,
        };
    }

    /**
     * 通道 handlers 声明的日志文件路径：RotatingFileHandler/StreamHandler 构造
     * 参数首位即文件名；非文件型 handler 首位参数不是字符串，自然跳过。
     *
     * @param array<mixed> $channel
     * @return list<string>
     */
    private static function handlerFiles(array $channel): array
    {
        $files = [];
        $handlers = $channel['handlers'] ?? [];
        if (!is_array($handlers)) {
            return $files;
        }
        foreach ($handlers as $handler) {
            if (!is_array($handler)) {
                continue;
            }
            $args = $handler['constructor'] ?? [];
            if (is_array($args) && isset($args[0]) && is_string($args[0]) && $args[0] !== '') {
                $files[] = $args[0];
            }
        }

        return $files;
    }
}
