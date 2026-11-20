<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Migration;

/**
 * 半成品库检测：账本与迁移文件目录逐项核对，纯函数、可单测。
 * 违例即半成品库——执行器必须拒绝继续，并给出修复指引。
 */
final class LedgerConsistency
{
    /**
     * @param list<MigrationFile> $files 迁移文件（按命名空间、版本排序）
     * @param list<LedgerRow> $rows 账本全部行
     * @return list<string> 违例描述；空数组 = 一致
     */
    public static function problems(array $files, array $rows): array
    {
        $problems = [];
        $fileByKey = [];
        $versionsByNs = [];
        foreach ($files as $file) {
            $fileByKey[$file->namespace . '' . $file->version] = $file;
            $versionsByNs[$file->namespace][] = $file->version;
        }

        $rowsByNs = [];
        foreach ($rows as $row) {
            $rowsByNs[$row->namespace][] = $row;
            $key = $row->namespace . '' . $row->version;
            $file = $fileByKey[$key] ?? null;
            if ($row->status === 'failed') {
                $problems[] = "账本存在 failed 记录 {$row->namespace}/{$row->version}"
                    . "（direction={$row->direction}）：上次执行中断，库结构可能停在半改状态";
            }
            if (!in_array($row->status, ['applied', 'failed', 'rolled_back'], true)) {
                $problems[] = "账本 {$row->namespace}/{$row->version} 的 status 非法：{$row->status}";
            }
            if ($file === null) {
                $problems[] = "账本 {$row->namespace}/{$row->version}"
                    . "（{$row->filename}）在迁移目录里找不到对应文件";
                continue;
            }
            if ($row->status === 'applied'
                && $row->direction === 'up'
                && !hash_equals($file->checksum, $row->checksum)) {
                $problems[] = "已应用的迁移文件被改动：{$row->namespace}/{$row->version}"
                    . '（账本 checksum 与文件 sha256 不符）';
            }
            if ($row->status === 'rolled_back'
                && $row->direction === 'down'
                && $file->hasDown()
                && !hash_equals($file->downChecksum(), $row->checksum)) {
                $problems[] = "回退文件被改动：{$row->namespace}/{$row->version}"
                    . '（账本 checksum 与 .down.sql sha256 不符）';
            }
        }

        foreach ($versionsByNs as $namespace => $versions) {
            $applied = [];
            $recorded = [];
            foreach ($rowsByNs[$namespace] ?? [] as $row) {
                $recorded[] = $row->version;
                if ($row->status === 'applied') {
                    $applied[] = $row->version;
                }
            }
            sort($applied, SORT_STRING);
            sort($recorded, SORT_STRING);
            $prefix = array_slice($versions, 0, count($applied));
            if ($applied !== $prefix) {
                $problems[] = "命名空间 {$namespace} 账本与迁移序列对不上："
                    . 'applied=[' . implode(',', $applied) . ']，'
                    . '按文件序列应是前缀 [' . implode(',', $prefix) . ']';
            }
            if ($recorded !== []) {
                $boundary = $recorded[count($recorded) - 1];
                foreach ($versions as $version) {
                    if ($version <= $boundary && !in_array($version, $recorded, true)) {
                        $problems[] = "命名空间 {$namespace} 的版本 {$version}"
                            . '排在已记账版本之前却没有账本行（账本可能丢行）';
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * 违例列表 + 人工修复指引，拼成拒绝执行的报错文本。
     *
     * @param list<string> $problems
     */
    public static function guidance(array $problems): string
    {
        $lines = array_merge(['半成品库检测未通过，拒绝继续执行：'], $problems);
        $lines[] = '修复指引（按违例类型处理，修完重跑）：';
        $lines[] = '- failed 记录：该迁移上次执行中断。人工核对实际结构后，把该行 status'
            . ' 改为 applied 或 rolled_back；或从恢复点恢复后重跑迁移。';
        $lines[] = '- checksum 不符：确认是不是有人改了已入仓迁移；是则改回原文'
            . '（已发布迁移不改内容）。库内结构与文件确实不一致时，人工对齐结构后修正账本。';
        $lines[] = '- 序列断档/丢行：人工核对每份迁移的实际状态，修正账本后重跑。';

        return implode("\n", $lines);
    }
}
