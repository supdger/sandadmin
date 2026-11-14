<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Monitor;

use Doctrine\DBAL\Connection;

/**
 * 运行状态采样：CPU、内存取「本系统」= 与当前进程同一进程组的全部进程
 * （webman 主进程与全部 worker），磁盘取整机分区。非 Linux 环境读不到 /proc
 * 时对应项返回 null，列允许为空。样本写入 sand_system_metric_sample。
 */
final class MetricSampler
{
    private const PROC_GLOB = '/proc/[0-9]*/stat';
    private const KILOBYTE = 1048576;
    private const GIGABYTE = 1073741824;

    /** @var array{jiffies: int, wall: float}|null 上次采样的进程组 CPU 时间与墙钟 */
    private static ?array $lastCpu = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, int|float|bool|null> */
    public function collect(): array
    {
        $processMb = $this->groupMemoryMb();
        $totalMb = $this->hostMemoryTotalMb();

        return [
            'cpu_usage_percent' => $this->cpuUsagePercent(),
            'cpu_cores' => self::cores(),
            'memory_used_mb' => $processMb,
            'memory_total_mb' => $totalMb,
            'memory_usage_percent' => $processMb !== null && $totalMb !== null && $totalMb > 0
                ? round($processMb / $totalMb * 100, 2)
                : null,
            'disk_free_gb' => $this->diskGb(false),
            'disk_total_gb' => $this->diskGb(true),
            'process_memory_mb' => $processMb,
            'process_uptime_seconds' => $this->uptimeSeconds(),
            'db_ok' => $this->dbOk(),
        ];
    }

    /**
     * 落一条采样行；同一秒重复采样按主键冲突忽略。
     *
     * @param array<string, mixed> $sample
     */
    public function persist(array $sample, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO sand_system_metric_sample ('
            . ' collected_time, cpu_usage_percent, cpu_cores, memory_used_mb, memory_total_mb,'
            . ' memory_usage_percent, disk_free_gb, disk_total_gb, process_memory_mb, process_uptime_seconds, db_ok'
            . ' ) VALUES ('
            . ' :collected_time, :cpu, :cores, :mem_used, :mem_total, :mem_pct, :disk_free, :disk_total, :proc_mem, :proc_up, :db_ok'
            . ' ) ON CONFLICT (collected_time) DO NOTHING',
            [
                'collected_time' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:sP'),
                'cpu' => $sample['cpu_usage_percent'],
                'cores' => $sample['cpu_cores'],
                'mem_used' => $sample['memory_used_mb'],
                'mem_total' => $sample['memory_total_mb'],
                'mem_pct' => $sample['memory_usage_percent'],
                'disk_free' => $sample['disk_free_gb'],
                'disk_total' => $sample['disk_total_gb'],
                'proc_mem' => $sample['process_memory_mb'],
                'proc_up' => $sample['process_uptime_seconds'],
                'db_ok' => ($sample['db_ok'] ?? false) === true ? 'true' : 'false',
            ],
        );
    }

    /** 进程组 CPU 时间增量占比：两次采样间进程组耗费的核秒 / (墙钟秒 × 核数) × 100。 */
    private function cpuUsagePercent(): ?float
    {
        $times = $this->groupCpuTimes();
        if ($times === null) {
            return null;
        }
        $wall = microtime(true);
        $cores = self::cores() ?? 1;
        $prev = self::$lastCpu;
        self::$lastCpu = ['jiffies' => $times['jiffies'], 'wall' => $wall];
        $clockTick = self::clockTick();
        if ($prev !== null) {
            $elapsed = $wall - $prev['wall'];
            if ($elapsed > 0) {
                return round(max(0.0, ($times['jiffies'] - $prev['jiffies']) / $clockTick / ($elapsed * $cores) * 100), 2);
            }
        }
        if ($times['uptime_seconds'] > 0) {
            return round($times['jiffies'] / $clockTick / ($times['uptime_seconds'] * $cores) * 100, 2);
        }

        return null;
    }

    /**
     * 本系统（同进程组）全部进程的 CPU  jiffies 合计与最早启动至今的秒数。
     *
     * @return array{jiffies: int, uptime_seconds: int}|null
     */
    private function groupCpuTimes(): ?array
    {
        $pgid = self::groupId();
        if ($pgid === null) {
            return null;
        }
        $jiffies = 0;
        $earliest = null;
        foreach (glob(self::PROC_GLOB) ?: [] as $stat) {
            $parts = self::statFields($stat);
            if ($parts === null || (int) $parts[5] !== $pgid) {
                continue;
            }
            $jiffies += (int) $parts[13] + (int) $parts[14];
            $start = (int) $parts[21];
            $earliest = $earliest === null ? $start : min($earliest, $start);
        }
        if ($jiffies <= 0 || $earliest === null) {
            return null;
        }
        $bootAge = $this->bootUptimeSeconds();

        return ['jiffies' => $jiffies, 'uptime_seconds' => (int) max(0, $bootAge - $earliest / self::clockTick())];
    }

    /** 本系统（同进程组）全部进程 RSS 合计，单位 MB。 */
    private function groupMemoryMb(): ?int
    {
        $pgid = self::groupId();
        if ($pgid === null) {
            return (int) round(memory_get_usage(true) / self::KILOBYTE);
        }
        $pageSize = function_exists('posix_sysconf') ? posix_sysconf(POSIX_SC_PAGESIZE) : 4096;
        if (!is_int($pageSize) || $pageSize <= 0) {
            $pageSize = 4096;
        }
        $kb = 0;
        foreach (glob('/proc/[0-9]*/statm') ?: [] as $statm) {
            $stat = self::statFields(dirname($statm) . '/stat');
            if ($stat === null || (int) $stat[5] !== $pgid) {
                continue;
            }
            $fields = preg_split('/\s+/', trim((string) @file_get_contents($statm)));
            if (is_array($fields) && isset($fields[1]) && is_numeric($fields[1])) {
                $kb += (int) $fields[1] * $pageSize / 1024;
            }
        }

        return $kb > 0 ? (int) round($kb / 1024) : null;
    }

    /** 整机内存总量（MemTotal），单位 MB；非 Linux 返回 null。 */
    private function hostMemoryTotalMb(): ?int
    {
        $info = @file_get_contents('/proc/meminfo');
        if (is_string($info) && preg_match('/^MemTotal:\s+(\d+)\s+kB/m', $info, $match) === 1) {
            return (int) round((int) $match[1] / 1024);
        }
        if (function_exists('shell_exec')) {
            $bytes = @shell_exec('sysctl -n hw.memsize 2>/dev/null');
            if (is_string($bytes) && is_numeric(trim($bytes))) {
                return (int) round((float) trim($bytes) / self::KILOBYTE);
            }
        }

        return null;
    }

    private function diskGb(bool $total): ?float
    {
        $base = defined('BASE_PATH') ? constant('BASE_PATH') : getcwd();
        if (!is_string($base)) {
            return null;
        }
        $bytes = $total ? @disk_total_space($base) : @disk_free_space($base);
        if ($bytes === false || $bytes <= 0) {
            return null;
        }

        return round($bytes / self::GIGABYTE, 2);
    }

    /** 当前进程运行秒数；读不到进程启动时刻时退回整机已运行时长，都读不到返回 null。 */
    private function uptimeSeconds(): ?int
    {
        $bootAge = $this->bootUptimeSeconds();
        $stat = self::statFields('/proc/self/stat');
        if ($stat === null) {
            // macOS 没有 /proc，进程启动时刻拿不到，用整机已运行时长近似
            return $bootAge === null ? null : (int) $bootAge;
        }
        if ($bootAge === null) {
            return null;
        }

        return (int) max(0, $bootAge - (int) $stat[21] / self::clockTick());
    }

    /** 系统启动至今秒数：Linux 读 /proc/uptime，macOS 用 kern.boottime 反推。 */
    private function bootUptimeSeconds(): ?float
    {
        $raw = @file_get_contents('/proc/uptime');
        if (is_string($raw)) {
            $parts = preg_split('/\s+/', trim($raw));
            if (is_array($parts) && isset($parts[0]) && is_numeric($parts[0])) {
                return (float) $parts[0];
            }
        }
        if (function_exists('shell_exec')) {
            $out = @shell_exec('sysctl -n kern.boottime 2>/dev/null');
            if (is_string($out) && preg_match('/sec\s*=\s*(\d+)/', $out, $match) === 1) {
                return max(0.0, microtime(true) - (float) $match[1]);
            }
            if (is_string($out) && is_numeric(trim($out))) {
                return max(0.0, microtime(true) - (float) trim($out));
            }
        }

        return null;
    }

    /**
     * /proc/<pid>/stat 按字段拆开；comm 可能含空格，先按最后一个右括号切。
     *
     * @return list<string>|null
     */
    private static function statFields(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            return null;
        }
        $close = strrpos($raw, ')');
        if ($close === false) {
            return null;
        }
        $pid = substr($raw, 0, strpos($raw, '(') ?: 0);
        $rest = preg_split('/\s+/', trim(substr($raw, $close + 1)));
        if (!is_array($rest) || count($rest) < 20) {
            return null;
        }

        return array_merge([trim($pid)], $rest);
    }

    private static function groupId(): ?int
    {
        if (!function_exists('posix_getpgid')) {
            return null;
        }
        $pgid = posix_getpgid(0);

        return is_int($pgid) ? $pgid : null;
    }

    private static function clockTick(): int
    {
        if (!function_exists('posix_sysconf')) {
            return 100;
        }
        $tick = posix_sysconf(POSIX_SC_CLK_TCK);

        return is_int($tick) && $tick > 0 ? $tick : 100;
    }

    private static function cores(): ?int
    {
        $info = @file_get_contents('/proc/cpuinfo');
        if (is_string($info)) {
            $count = substr_count($info, 'processor');
            if ($count > 0) {
                return $count;
            }
        }
        if (function_exists('shell_exec')) {
            $out = @shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if (is_string($out) && is_numeric(trim($out)) && (int) trim($out) > 0) {
                return (int) trim($out);
            }
        }

        return null;
    }

    private function dbOk(): bool
    {
        try {
            return $this->connection->fetchOne('SELECT 1') !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
