<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

use DateInterval;
use DateTimeImmutable;

/**
 * 文件日志清理：删除滚动日志里早于通道留存天数的 <name>-YYYY-MM-DD.<ext> 文件
 * （Monolog RotatingFileHandler 按天滚动命名）。边界只认各通道配置日志文件
 * 所在目录的 realpath：符号链接一律不删，realpath 解析失败或解析结果越出
 * 所在目录的条目也不删，清理动作绝不越出日志目录。活动日志（无日期段）与
 * 非本通道文件不受影响。
 */
final class LogFileCleaner
{
    /**
     * @return list<array{channel:string,days:int,deleted_count:int,skipped_count:int}> 每个清理目标一行
     */
    public function clean(LogRetentionPolicy $policy, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $rows = [];
        foreach ($policy->listFileTargets() as $target) {
            $rows[] = $this->cleanTarget($target, $today);
        }

        return $rows;
    }

    /**
     * @param array{channel:string,days:int,path:string} $target
     * @return array{channel:string,days:int,deleted_count:int,skipped_count:int}
     */
    private function cleanTarget(array $target, DateTimeImmutable $today): array
    {
        $row = [
            'channel' => $target['channel'],
            'days' => $target['days'],
            'deleted_count' => 0,
            'skipped_count' => 0,
        ];
        $dir = realpath(dirname($target['path']));
        if ($dir === false || !is_dir($dir)) {
            return $row;
        }
        $base = preg_quote(pathinfo($target['path'], PATHINFO_FILENAME), '/');
        $ext = preg_quote(pathinfo($target['path'], PATHINFO_EXTENSION), '/');
        $pattern = '/^' . $base . '-(\d{4}-\d{2}-\d{2})\.' . $ext . '$/';
        $cutoff = $today->sub(new DateInterval('P' . $target['days'] . 'D'));
        $entries = scandir($dir);
        if ($entries === false) {
            return $row;
        }
        foreach ($entries as $entry) {
            if (preg_match($pattern, $entry, $match) !== 1) {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
            // 日期非法或仍在留存期内不删；恰好 days 天前的边界日属留存期内
            if ($date === false || $date >= $cutoff) {
                continue;
            }
            $file = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_link($file) || !is_file($file)) {
                $row['skipped_count']++;
                continue;
            }
            $resolved = realpath($file);
            if ($resolved === false || !str_starts_with($resolved, $dir . DIRECTORY_SEPARATOR)) {
                $row['skipped_count']++;
                continue;
            }
            if (unlink($file)) {
                $row['deleted_count']++;
            }
        }

        return $row;
    }
}
