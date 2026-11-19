<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Id;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Id\SnowflakeGenerator;
use Sandadmin\Infra\Id\SnowflakeNodePlan;

/**
 * 跨进程唯一性：按 SnowflakeNodePlan 给每个进程槽位各起一个真实 PHP 子进程
 * 并发发号（不起服务、不连库），合并全部输出必须零重复。
 */
final class SnowflakeCrossProcessTest extends TestCase
{
    private const BASE_NODE = 100;
    private const IDS_PER_WORKER = 300;

    public function testIdsAcrossChildProcessesAreUnique(): void
    {
        $processConfig = [
            'webman' => ['count' => 4],
            'task_worker' => ['count' => 2],
            'log_retention' => ['count' => 1],
        ];
        $plan = SnowflakeNodePlan::fromConfigs($processConfig);
        $script = dirname(__DIR__, 3) . '/Support/snowflake-child.php';
        $outDir = sys_get_temp_dir() . '/snowflake-' . bin2hex(random_bytes(6));
        mkdir($outDir);
        $procs = [];
        $files = [];
        try {
            foreach ($processConfig as $name => $config) {
                for ($id = 0; $id < $config['count']; $id++) {
                    $nodeId = $plan->resolveNodeId(self::BASE_NODE, $name, $id);
                    $file = sprintf('%s/%s-%d.txt', $outDir, $name, $id);
                    $files[$file] = $nodeId;
                    $pipes = [];
                    $procs[$file] = proc_open(
                        [PHP_BINARY, $script, (string) $nodeId, (string) self::IDS_PER_WORKER],
                        [
                            0 => ['file', '/dev/null', 'r'],
                            1 => ['file', $file, 'w'],
                            2 => ['file', $file . '.err', 'w'],
                        ],
                        $pipes,
                    );
                    self::assertIsResource($procs[$file]);
                }
            }
            foreach ($procs as $file => $proc) {
                if (!is_resource($proc)) {
                    self::fail(sprintf('子进程未能拉起：%s', $file));
                }
                $exitCode = proc_close($proc);
                unset($procs[$file]);
                $stderr = (string) file_get_contents($file . '.err');
                self::assertSame(0, $exitCode, sprintf('子进程发号失败：%s', $stderr));
            }
            $all = [];
            foreach ($files as $file => $nodeId) {
                $lines = array_values(array_filter(
                    explode("\n", (string) file_get_contents($file)),
                    static fn (string $line): bool => $line !== '',
                ));
                self::assertCount(self::IDS_PER_WORKER, $lines, sprintf('子进程输出个数不符：%s', $file));
                $ids = array_map('intval', $lines);
                foreach ($ids as $id) {
                    self::assertSame($nodeId, SnowflakeGenerator::decode($id)['node_id']);
                }
                $all = array_merge($all, $ids);
            }
            $expected = $plan->workerTotal * self::IDS_PER_WORKER;
            self::assertCount($expected, $all);
            self::assertSame($expected, count(array_unique($all)), '跨进程发号出现重复');
        } finally {
            foreach ($procs as $proc) {
                if (is_resource($proc)) {
                    proc_close($proc);
                }
            }
            foreach (glob($outDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($outDir)) {
                rmdir($outDir);
            }
        }
    }
}
