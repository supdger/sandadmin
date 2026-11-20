<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Id;

/**
 * 本机发号节点布局：把全部会启动的进程按 webman 启动顺序排成连续槽位，
 * 进程内第 i 个 worker 拿「节点基值 + 槽位偏移 + i」。
 * 进程清单与 worker_start 同口径：config('process') 在前，
 * config('plugin') 的 plugin.{firm}[.{name}].{process} 在后；
 * enable=false 的进程不启动，不占槽位。
 */
final class SnowflakeNodePlan
{
    /**
     * @param array<string,array{offset:int,count:int}> $slots 进程名 => 槽位段
     * @param int $workerTotal 本机发号进程（worker）总数
     */
    private function __construct(
        private readonly array $slots,
        public readonly int $workerTotal,
    ) {
    }

    /**
     * @param array<mixed> $processConfig config('process') 原文
     * @param array<mixed> $pluginConfig config('plugin') 原文
     */
    public static function fromConfigs(array $processConfig, array $pluginConfig = []): self
    {
        $slots = [];
        $offset = 0;
        foreach (self::collectWorkerSlots($processConfig, $pluginConfig) as $name => $count) {
            $slots[$name] = ['offset' => $offset, 'count' => $count];
            $offset += $count;
        }

        return new self($slots, $offset);
    }

    /**
     * 本进程节点号 = 基值 + 槽位偏移 + worker 序号。
     *
     * @throws SnowflakeConfigException 进程不在布局内（拿不到号）或 worker 序号越界
     */
    public function resolveNodeId(int $base, string $processName, int $workerId): int
    {
        $slot = $this->slots[$processName] ?? null;
        if ($slot === null) {
            $label = $processName === '' ? '(未识别的进程)' : $processName;
            throw new SnowflakeConfigException(sprintf('进程 %s 不在发号布局内，无法分配节点号', $label));
        }
        if ($workerId < 0 || $workerId >= $slot['count']) {
            throw new SnowflakeConfigException(sprintf('进程 %s 的 worker 序号 %d 越界（共 %d 个槽位）', $processName, $workerId, $slot['count']));
        }

        return $base + $slot['offset'] + $workerId;
    }

    /**
     * @param array<mixed> $processConfig
     * @param array<mixed> $pluginConfig
     *
     * @return array<string,int> 进程名 => worker 数，顺序同 webman 启动顺序
     */
    private static function collectWorkerSlots(array $processConfig, array $pluginConfig): array
    {
        $counts = [];
        foreach ($processConfig as $name => $config) {
            $count = self::countWorkers($config);
            if ($count > 0) {
                $counts[(string) $name] = $count;
            }
        }
        foreach ($pluginConfig as $firm => $projects) {
            if (!is_array($projects)) {
                continue;
            }
            foreach ($projects as $name => $project) {
                if (!is_array($project)) {
                    continue;
                }
                $processes = $project['process'] ?? [];
                if (!is_array($processes)) {
                    continue;
                }
                foreach ($processes as $processName => $config) {
                    $count = self::countWorkers($config);
                    if ($count > 0) {
                        $counts[sprintf('plugin.%s.%s.%s', (string) $firm, (string) $name, (string) $processName)] = $count;
                    }
                }
            }
            $firmProcesses = $projects['process'] ?? [];
            if (is_array($firmProcesses)) {
                foreach ($firmProcesses as $processName => $config) {
                    $count = self::countWorkers($config);
                    if ($count > 0) {
                        $counts[sprintf('plugin.%s.%s', (string) $firm, (string) $processName)] = $count;
                    }
                }
            }
        }

        return $counts;
    }

    /** count 缺省按 webman 默认 1 个 worker。 */
    private static function countWorkers(mixed $config): int
    {
        if (!is_array($config) || (isset($config['enable']) && !$config['enable'])) {
            return 0;
        }
        $count = $config['count'] ?? 1;
        if (!is_int($count)) {
            $count = is_numeric($count) ? (int) $count : 1;
        }

        return max(1, $count);
    }
}
