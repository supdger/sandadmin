<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Contract;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Build\ContractBuild;

/**
 * collectPlugins 全量构建：合法插件片段出产物，冲突片段在写产物前被拦。
 * 用临时目录拼迷你仓（宿主契约 + server/plugin/<app>/contract/openapi.yaml），
 * 不碰真实仓与真实插件。
 */
final class CollectPluginsTest extends TestCase
{
    private string $repoDir = '';

    protected function tearDown(): void
    {
        if ($this->repoDir !== '' && is_dir($this->repoDir)) {
            self::removeTree($this->repoDir);
            $this->repoDir = '';
        }
    }

    public function testValidFragmentPassesAndWritesPluginRoutes(): void
    {
        $repo = $this->makeRepo();
        self::copyTree(self::fixtureDir('sand-hello'), $repo . '/server/plugin/sand-hello');

        $report = ContractBuild::collectPlugins($repo);

        self::assertSame('ok', $report['result'], implode("\n", $report['warnings']));
        $list = file_get_contents($repo . '/server/config/plugin-routes.generated.json');
        self::assertIsString($list);
        self::assertStringContainsString('plugin/sand-hello/config/route.php', $list);
        self::assertFileExists($repo . '/server/plugin/sand-hello/config/route.generated.php');
    }

    public function testConflictFailsBeforeAnyArtifact(): void
    {
        $repo = $this->makeRepo();
        $this->writeFragment($repo, 'sand-bad', <<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            x-error-catalog:
              NOT_FOUND: {http_status: 404, summary: 重复声明宿主已登记的码}
            paths:
              /api/admin/v1/plugin/sand-bad/things:
                get:
                  operationId: sandBadThingsIndex
                  summary: 坏例子
                  x-handler: 'SandBad\Http\ThingController::index'
                  x-permission: sand_bad:thing:index
                  x-error-codes: [NOT_FOUND]
                  responses: {'200': {description: ok}}
            YAML);

        $report = ContractBuild::collectPlugins($repo);

        self::assertSame('failed', $report['result']);
        $text = implode("\n", $report['warnings']);
        self::assertStringContainsString('NOT_FOUND', $text);
        self::assertStringContainsString('sand-bad', $text);
        self::assertStringContainsString('host', $text);
        // 冲突在写产物之前返回：四类产物一个都不能落盘
        self::assertFileDoesNotExist($repo . '/server/config/route.generated.php');
        self::assertFileDoesNotExist($repo . '/server/config/plugin-routes.generated.json');
        self::assertFileDoesNotExist($repo . '/server/src/Contract/Generated/ErrorCodeRegistry.php');
        self::assertFileDoesNotExist($repo . '/server/plugin/sand-bad/config/route.generated.php');
    }

    public function testWriteRemovesOrphanGeneratedFiles(): void
    {
        // 契约不再产出的旧骨架留在 Generated 目录时，收集要把它们一并清掉：
        // 否则产物重建不完整，--check 立即报孤儿要求手工跑 contract-build
        $repo = $this->makeRepo();
        $orphanDir = $repo . '/server/tests/Contract/Generated';
        if (!is_dir($orphanDir) && !mkdir($orphanDir, 0755, true)) {
            self::fail('无法创建生成目录');
        }
        file_put_contents($orphanDir . '/GhostContractTest.php', '<?php // 过期骨架');

        $report = ContractBuild::collectPlugins($repo);

        self::assertSame('ok', $report['result'], implode("\n", $report['warnings']));
        self::assertFileDoesNotExist($orphanDir . '/GhostContractTest.php');
        self::assertSame('ok', ContractBuild::check($repo)['result']);
    }

    public function testConflictEntriesKeepBothSources(): void
    {
        $repo = $this->makeRepo();
        $this->writeFragment($repo, 'sand-bad', <<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            x-error-catalog:
              NOT_FOUND: {http_status: 404, summary: 重复声明宿主已登记的码}
            paths: {}
            YAML);

        $report = ContractBuild::collectPlugins($repo);

        self::assertSame('failed', $report['result']);
        $detail = implode("\n", array_merge($report['problems'], $report['conflicts']));
        self::assertStringContainsString('host', $detail);
        self::assertStringContainsString('sand-bad', $detail);
        self::assertStringContainsString('NOT_FOUND', $detail);
    }

    public function testConnectionBudgetArtifactWritten(): void
    {
        $repo = $this->makeRepo();
        self::copyTree(self::fixtureDir('sand-hello'), $repo . '/server/plugin/sand-hello');

        $report = ContractBuild::build($repo);

        self::assertSame('ok', $report['result'], implode("\n", $report['warnings']));
        // sand-hello 夹具声明两条命名连接：2 + 1 = 每进程 3 条
        self::assertSame(3, array_sum(array_column($report['named_connections'], 'connections_per_process')));
        $file = $repo . '/server/config/connection-budget.generated.json';
        self::assertFileExists($file);
        $budget = json_decode((string) file_get_contents($file), true);
        self::assertIsArray($budget);
        self::assertSame(4, $budget['host_per_process']);
        self::assertSame(3, $budget['plugin_per_process']);
        self::assertSame(7, $budget['total_per_process']);
    }

    public function testBadConnectionFragmentBlocked(): void
    {
        $repo = $this->makeRepo();
        $this->writeFragment($repo, 'sand-bad', <<<'YAML'
            openapi: 3.1.0
            info: {title: bad, version: "0.1"}
            x-connections:
              - name: sandai
                connections_per_process: 2
                ledger: private
                outbox: none
                audit_sink: none
            paths: {}
            YAML);

        $report = ContractBuild::collectPlugins($repo);

        self::assertSame('failed', $report['result']);
        self::assertStringContainsString('per_connection', implode("\n", $report['warnings']));
        self::assertFileDoesNotExist($repo . '/server/config/connection-budget.generated.json');
    }

    private function makeRepo(): string
    {
        $dir = sys_get_temp_dir() . '/contract-collect-' . getmypid() . '-' . bin2hex(random_bytes(4));
        foreach (['contract', 'server/plugin'] as $sub) {
            if (!is_dir($dir . '/' . $sub) && !mkdir($dir . '/' . $sub, 0755, true)) {
                self::fail('无法创建临时仓目录 ' . $sub);
            }
        }
        $source = self::repoRoot() . '/contract/openapi.yaml';
        if (!copy($source, $dir . '/contract/openapi.yaml')) {
            self::fail('无法复制宿主契约到临时仓');
        }
        $this->repoDir = $dir;

        return $dir;
    }

    private function writeFragment(string $repo, string $app, string $yaml): void
    {
        $dir = $repo . '/server/plugin/' . $app . '/contract';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            self::fail('无法创建插件片段目录');
        }
        file_put_contents($dir . '/openapi.yaml', $yaml);
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    private static function fixtureDir(string $app): string
    {
        return self::repoRoot() . '/server/tests/Fixtures/plugin/' . $app;
    }

    private static function copyTree(string $from, string $to): void
    {
        if (!is_dir($to) && !mkdir($to, 0755, true)) {
            self::fail('无法创建目录 ' . $to);
        }
        $items = scandir($from);
        if ($items === false) {
            self::fail('无法读取目录 ' . $from);
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $from . '/' . $item;
            $dst = $to . '/' . $item;
            if (is_dir($src)) {
                self::copyTree($src, $dst);
            } elseif (!copy($src, $dst)) {
                self::fail('无法复制 ' . $src);
            }
        }
    }

    private static function removeTree(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
