<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\Build;

use Sandadmin\Contract\SourceHash;
use Sandadmin\Contract\KernelApi\RuntimeCheck;

/**
 * 契约构建编排：收集（宿主契约 + 插件片段）→ 解析 → 冲突检查 → 发射产物。
 * tools/contract-build 只是薄壳，全部逻辑在这里，测试直接调本类。
 *
 * build 产物落位：
 *   server/config/route.generated.php            宿主路由表
 *   server/config/plugin-routes.generated.json   插件路由入口清单
 *   server/src/Contract/Generated/*.php          三张注册表
 *   web/src/api/v1/contract.generated.ts         前端契约类型
 *   server/tests/Contract/Generated/*.php        测试骨架
 *   server/plugin/<app>/config/route.*           契约插件的自身路由表
 *
 * @phpstan-import-type ParsedDoc from ContractDoc
 */
final class ContractBuild
{
    /**
     * @return array{
     *   result: string,
     *   conflicts: list<string>,
     *   problems: list<string>,
     *   warnings: list<string>,
     *   artifacts: list<array{path: string, status: string}>,
     *   incomplete_operations: list<string>,
     *   source_hash: string,
     * }
     */
    public static function build(string $repoRoot, bool $write = true): array
    {
        $serverRoot = $repoRoot . '/server';
        $report = [
            'result' => 'ok',
            'conflicts' => [],
            'problems' => [],
            'warnings' => [],
            'artifacts' => [],
            'incomplete_operations' => [],
            'source_hash' => '',
        ];

        $collection = self::collect($repoRoot, $serverRoot);
        $report['problems'] = $collection['problems'];
        $report['warnings'] = $collection['warnings'];
        $docs = $collection['docs'];
        $pluginStems = $collection['plugin_stems'];

        if ($collection['problems'] !== []) {
            $report['result'] = 'failed';
            return $report;
        }

        $conflicts = ConflictCheck::check($docs, $pluginStems);
        $report['conflicts'] = $conflicts;
        if ($conflicts !== []) {
            $report['result'] = 'failed';
            return $report;
        }

        $sourceHash = SourceHash::compute($collection['sources']);
        $report['source_hash'] = $sourceHash;
        $sourceLabels = array_map(
            static fn(string $rel): string => $rel,
            array_keys($collection['sources']),
        );

        $hostOps = [];
        $pluginOps = [];
        foreach ($docs as $source => $doc) {
            foreach ($doc['operations'] as $op) {
                if ($source === 'host') {
                    $hostOps[] = $op;
                } else {
                    $pluginOps[$source][] = $op;
                }
            }
        }

        $permissions = ConflictCheck::collectPermissions($docs);
        $catalog = ConflictCheck::collectErrors($docs);
        $tools = ConflictCheck::collectTools($docs);
        $tree = $docs['host']['tree'] ?? [];
        $sidebar = $docs['host']['sidebar'] ?? [];
        $adminCodes = $docs['host']['admin'] ?? [];

        $allOps = array_merge($hostOps, ...array_values($pluginOps === [] ? [[]] : $pluginOps));
        $report['incomplete_operations'] = array_values(array_map(
            static fn(array $op): string => $op['operation_id'],
            array_filter($allOps, static fn(array $op): bool => $op['operation_id'] !== ''),
        ));

        $artifacts = [];
        $artifacts['server/config/route.generated.php'] =
            PhpEmit::emitRoutes($hostOps, $sourceLabels, $sourceHash);
        $artifacts['server/config/plugin-routes.generated.json'] =
            PhpEmit::emitPluginRoutes($collection['route_entries'], $sourceLabels, $sourceHash);
        $artifacts['server/src/Contract/Generated/PermissionRegistry.php'] =
            PhpEmit::emitPermissionRegistry($permissions, $tree, $adminCodes, $sidebar, $sourceLabels, $sourceHash);
        $artifacts['server/src/Contract/Generated/McpToolRegistry.php'] =
            PhpEmit::emitMcpRegistry($tools, $sourceLabels, $sourceHash);
        $artifacts['server/src/Contract/Generated/ErrorCodeRegistry.php'] =
            PhpEmit::emitErrorRegistry($catalog, $sourceLabels, $sourceHash);
        $artifacts['web/src/api/v1/contract.generated.ts'] =
            TsEmit::emit($allOps, $catalog, $permissions, $tools, $sourceLabels, $sourceHash);

        foreach (SkeletonEmit::emitAll($docs, $sourceLabels, $sourceHash) as $rel => $content) {
            $artifacts['server/tests/Contract/Generated/' . $rel] = $content;
        }

        foreach ($pluginOps as $app => $ops) {
            $pluginBase = "server/plugin/{$app}/config";
            $artifacts["{$pluginBase}/route.generated.php"] =
                PhpEmit::emitPluginRouteTable($ops, $sourceLabels, $sourceHash);
            $artifacts["{$pluginBase}/route.php"] =
                PhpEmit::emitPluginRouteShell($app, $sourceLabels, $sourceHash);
        }

        if ($write) {
            foreach ($artifacts as $rel => $content) {
                $absolute = $repoRoot . '/' . $rel;
                self::writeFile($absolute, $content);
                $report['artifacts'][] = ['path' => $rel, 'status' => 'written'];
            }
        } else {
            $stale = self::compareArtifacts($repoRoot, $artifacts);
            foreach ($artifacts as $rel => $content) {
                $report['artifacts'][] = [
                    'path' => $rel,
                    'status' => in_array($rel, $stale, true) ? 'mismatch' : 'unchanged',
                ];
            }
            foreach ($stale as $rel) {
                $report['warnings'][] = "产物与契约不一致：{$rel}";
            }
            if ($stale !== []) {
                $report['result'] = 'failed';
            }
        }

        return $report;
    }

    /**
     * --check：产物与当前源逐字节比对，不写任何文件。
     * @return array{result: string, mismatches: list<string>, warnings: list<string>, problems: list<string>}
     */
    public static function check(string $repoRoot): array
    {
        $report = self::build($repoRoot, write: false);
        $mismatches = [];
        foreach ($report['artifacts'] as $artifact) {
            if ($artifact['status'] === 'mismatch') {
                $mismatches[] = $artifact['path'];
            }
        }

        return [
            'result' => $report['result'],
            'mismatches' => $mismatches,
            'warnings' => $report['warnings'],
            'problems' => array_merge($report['problems'], $report['conflicts']),
        ];
    }

    /**
     * 插件装/升/卸后由生命周期调用：重跑收集与产物落位。
     * warnings 是全部提示（含 problems/conflicts），调用方写失败原因时
     * 只取 problems + conflicts，免得把「无契约片段走旧机制」写进错误。
     * @return array{result: string, problems: list<string>, conflicts: list<string>, warnings: list<string>}
     */
    public static function collectPlugins(string $repoRoot): array
    {
        $report = self::build($repoRoot, write: true);

        return [
            'result' => $report['result'],
            'problems' => $report['problems'],
            'conflicts' => $report['conflicts'],
            'warnings' => array_merge($report['warnings'], $report['problems'], $report['conflicts']),
        ];
    }

    /**
     * kernel_api 模式：检查插件容器目录下各包的契约片段（只校验不落产物）。
     * $pluginRoot 是各插件包的上级目录（如 kernel_api 仓的 plugin/ 或
     * server/plugin）；缺片段 fail-closed；片段之间跑互冲突。
     * @return array{result: string, problems: list<string>, fragments: list<string>}
     */
    public static function checkKernelApi(string $pluginRoot, ?string $hostContractFile = null): array
    {
        $problems = [];
        $fragments = [];
        $docs = [];

        if ($hostContractFile !== null && is_file($hostContractFile)) {
            // 宿主契约并入：插件引用宿主已登记码不算未登记，撞宿主路由也算冲突
            $docs['host'] = ContractDoc::parse($hostContractFile, 'host');
        }

        if (!is_dir($pluginRoot)) {
            $problems[] = "插件目录不存在：{$pluginRoot}";
            return ['result' => 'failed', 'problems' => $problems, 'fragments' => []];
        }

        $apps = glob($pluginRoot . '/*', GLOB_ONLYDIR);
        if ($apps === false) {
            $apps = [];
        }
        sort($apps);
        foreach ($apps as $appDir) {
            $app = basename($appDir);
            $fragment = $appDir . '/contract/openapi.yaml';
            if (!is_file($fragment)) {
                $problems[] = "插件 {$app} 缺少契约片段：{$fragment}";
                continue;
            }
            $fragments[] = $app;
            $doc = ContractDoc::parse($fragment, $app);
            foreach ($doc['problems'] as $problem) {
                $problems[] = $problem;
            }
            // 有片段的包顺带核运行时对齐（薄壳/路由表/注解码/x-handler 文件）
            foreach (RuntimeCheck::check($appDir, $doc) as $problem) {
                $problems[] = $problem;
            }
            $docs[$app] = $doc;
        }

        if ($fragments === []) {
            $problems[] = "kernel_api 下没有任何契约片段（期望 <插件包>/contract/openapi.yaml）";
            return ['result' => 'failed', 'problems' => $problems, 'fragments' => $fragments];
        }

        $stems = [];
        foreach ($fragments as $app) {
            $stems[$app] = self::pluginStem($app);
        }
        foreach (ConflictCheck::check($docs, $stems) as $conflict) {
            $problems[] = $conflict;
        }

        return [
            'result' => $problems === [] ? 'ok' : 'failed',
            'problems' => $problems,
            'fragments' => $fragments,
        ];
    }

    /**
     * 收集宿主契约 + server/plugin 下片段；插件词干表与路由入口清单一并算出。
     * @return array{
     *   docs: array<string, ParsedDoc>,
     *   plugin_stems: array<string, string>,
     *   sources: array<string, string>,
     *   route_entries: list<string>,
     *   problems: list<string>,
     *   warnings: list<string>,
     * }
     */
    private static function collect(string $repoRoot, string $serverRoot): array
    {
        $docs = [];
        $stems = [];
        $warnings = [];
        $problems = [];
        $routeEntries = [];

        $hostFile = $repoRoot . '/contract/openapi.yaml';
        $docs['host'] = ContractDoc::parse($hostFile, 'host');
        foreach ($docs['host']['problems'] as $problem) {
            $problems[] = $problem;
        }

        $pluginRoot = $serverRoot . '/plugin';
        $apps = is_dir($pluginRoot) ? glob($pluginRoot . '/*', GLOB_ONLYDIR) : [];
        if ($apps === false) {
            $apps = [];
        }
        sort($apps);
        foreach ($apps as $appDir) {
            $app = basename($appDir);
            $fragment = $appDir . '/contract/openapi.yaml';
            $legacyRoute = $appDir . '/config/route.php';
            if (is_file($fragment)) {
                $docs[$app] = ContractDoc::parse($fragment, $app);
                $stems[$app] = self::pluginStem($app);
                foreach ($docs[$app]['problems'] as $problem) {
                    $problems[] = $problem;
                }
                // 契约插件入口是生成的薄壳 route.php（转发到 route.generated.php）
                $routeEntries[] = "plugin/{$app}/config/route.php";
                continue;
            }
            if (is_file($legacyRoute)) {
                // 无契约片段的插件回退旧机制，记录 warning 让清单可审计
                $routeEntries[] = "plugin/{$app}/config/route.php";
                $warnings[] = "插件 {$app} 无契约片段，路由走旧机制（config/route.php）";
            }
        }

        $sources = SourceHash::sourcesOf($repoRoot, $serverRoot);
        // 只把解析到的片段计入哈希；目录存在但无 openapi.yaml 的目录不入参
        $kept = [];
        foreach ($sources as $rel => $abs) {
            if ($rel === 'contract/openapi.yaml' || is_file($abs)) {
                $kept[$rel] = $abs;
            }
        }

        return [
            'docs' => $docs,
            'plugin_stems' => $stems,
            'sources' => $kept,
            'route_entries' => $routeEntries,
            'problems' => $problems,
            'warnings' => $warnings,
        ];
    }

    // route.php 的哨兵结构与「哨兵段外不挂路由」断言在 tools/check-dev-refs.php：
    // 构建器不感知 dev 哨兵，那条是 dev 链门禁自己的职责。

    /**
     * @param array<string, string> $artifacts 相对路径 => 期望内容
     * @return list<string> 不一致（或缺失）的相对路径
     */
    private static function compareArtifacts(string $repoRoot, array $artifacts): array
    {
        $stale = [];
        foreach ($artifacts as $rel => $expected) {
            $absolute = $repoRoot . '/' . $rel;
            $actual = is_file($absolute) ? file_get_contents($absolute) : false;
            if ($actual === false || $actual !== $expected) {
                $stale[] = $rel;
            }
        }
        // 反向：生成目录里多出孤儿文件（如已删 tag 的骨架）也算不一致
        $generatedDir = $repoRoot . '/server/tests/Contract/Generated';
        if (is_dir($generatedDir)) {
            $files = glob($generatedDir . '/*.php');
            if ($files !== false) {
                foreach ($files as $file) {
                    $rel = 'server/tests/Contract/Generated/' . basename($file);
                    if (!isset($artifacts[$rel])) {
                        $stale[] = $rel . '（孤儿产物，应删除）';
                    }
                }
            }
        }

        return $stale;
    }

    private static function writeFile(string $absolute, string $content): void
    {
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('无法创建目录：' . $dir);
        }
        if (file_put_contents($absolute, $content) === false) {
            throw new \RuntimeException('无法写产物：' . $absolute);
        }
    }

    /**
     * 插件 app → 权限域词干：sand-foo → foo（域 sand_foo:，工具前缀 sand_foo_）。
     * 不带 sand- 前缀的 app 整体作词干（连字符转下划线）。
     */
    public static function pluginStem(string $app): string
    {
        $stem = str_starts_with($app, 'sand-') ? substr($app, 5) : $app;

        return str_replace('-', '_', $stem);
    }
}
