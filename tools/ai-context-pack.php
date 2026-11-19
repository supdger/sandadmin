#!/usr/bin/env php
<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

// AI 上下文打包：从 contract/openapi.yaml 与生成产物产出给 AI 读的摘要
// （接口清单、路由、权限码、错误码、MCP 工具表），默认写 dist/ai-context-pack.md。
//   php tools/ai-context-pack.php                      生成摘要包
//   php tools/ai-context-pack.php --out <path>         指定输出文件（可在仓库外）
//   php tools/ai-context-pack.php --max-bytes <n>      体积上限，默认 65536，超限退出 1
//   php tools/ai-context-pack.php --self-check         不落地，跑一致性自检，打印逐项结果

require __DIR__ . '/../server/vendor/autoload.php';

use Sandadmin\Contract\Build\ContractDoc;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Contract\Generated\McpToolRegistry;
use Sandadmin\Contract\Generated\PermissionRegistry;
use Sandadmin\Contract\SourceHash;

const CONTEXT_PACK_MAX_BYTES = 65536;

/** @return list<array{method: string, path: string, handler: string}> */
function readGeneratedRoutes(string $file): array
{
    $body = file_get_contents($file);
    if ($body === false) {
        throw new RuntimeException("无法读取 {$file}");
    }
    // 生成表用 use 导入短类名，按 use 表还原 FQCN 方便 AI 直接定位文件
    $imports = [];
    preg_match_all('/^use ([A-Za-z0-9_\\\\]+);/m', $body, $useMatch, PREG_SET_ORDER);
    foreach ($useMatch as $useRow) {
        $imports[substr($useRow[1], (int) strrpos($useRow[1], '\\') + 1)] = $useRow[1];
    }
    $routes = [];
    preg_match_all(
        "/Route::(get|post|put|patch|delete|head|options)\\('([^']+)'\\s*,\\s*\\[([A-Za-z0-9_\\\\]+)::class,\\s*'([^']+)'\\]\\)/",
        $body,
        $match,
        PREG_SET_ORDER,
    );
    foreach ($match as $row) {
        $class = str_contains($row[3], '\\') ? $row[3] : ($imports[$row[3]] ?? $row[3]);
        $routes[] = [
            'method' => strtoupper($row[1]),
            'path' => $row[2],
            'handler' => $class . '::' . $row[4],
        ];
    }

    return $routes;
}

/** @return array<string, string> 常量名 => 权限码 */
function readPermissionCodes(): array
{
    return (new ReflectionClass(PermissionRegistry::class))->getConstants();
}

/**
 * @param array<string, mixed> $doc
 * @param list<array{method: string, path: string, handler: string}> $routes
 */
function renderContextPack(array $doc, array $routes, string $contractHash): string
{
    $operations = $doc['operations'];
    $errors = $doc['errors'];
    $extraPermissions = $doc['permissions'];
    $tree = $doc['tree'];
    $admin = $doc['admin'];
    $mcpTools = $doc['mcp_tools'];
    foreach ($operations as $op) {
        if ($op['mcp'] !== null) {
            $mcpTools[] = $op['mcp'];
        }
    }

    $out = [];
    $out[] = '# SandAdmin 1.x AI 上下文包';
    $out[] = '';
    $out[] = '> 由 tools/ai-context-pack.php 生成。源哈希（与生成产物头部同源）：';
    $out[] = "> sha256:{$contractHash}。接口、权限码、错误码、MCP 工具的权威定义都在契约里，";
    $out[] = '> 本文件只做速查；改了契约要重新打包。';
    $out[] = '';
    $out[] = '## 统计';
    $out[] = '';
    $out[] = '- 接口 ' . count($operations)
        . ' 个，错误码 ' . count($errors)
        . ' 个，MCP 工具 ' . count($mcpTools) . ' 个';
    $out[] = '';
    $out[] = '## 接口清单';
    $out[] = '';
    $out[] = '| 方法 | 路径 | operationId | 说明 | 权限 | 业务错误码 |';
    $out[] = '| --- | --- | --- | --- | --- | --- |';
    foreach ($operations as $op) {
        $out[] = '| ' . $op['method']
            . ' | `' . $op['path'] . '`'
            . ' | ' . $op['operation_id']
            . ' | ' . oneLine($op['summary'])
            . ' | ' . renderPermission($op)
            . ' | ' . ($op['error_codes'] === [] ? '—' : implode(' ', $op['error_codes']))
            . ' |';
    }
    $out[] = '';
    $out[] = '## 路由表（server/config/route.generated.php）';
    $out[] = '';
    $out[] = '```';
    foreach ($routes as $route) {
        $out[] = $route['method'] . ' ' . $route['path'] . ' → ' . $route['handler'];
    }
    $out[] = '```';
    $out[] = '';
    $out[] = '## 权限码';
    $out[] = '';
    $out[] = '### 权限树（可经角色授予）';
    $out[] = '';
    foreach ($tree as $node) {
        $actions = array_map(
            fn (array $a): string => $node['menu_key'] . ':' . $a['action'] . '（' . $a['label'] . '）',
            $node['actions'],
        );
        $out[] = '- ' . $node['group_title'] . ' / ' . $node['title'] . '：' . implode('、', $actions);
    }
    $out[] = '';
    $out[] = '### 树外权限码';
    $out[] = '';
    foreach ($extraPermissions as $code => $title) {
        $out[] = '- `' . $code . '` ' . $title;
    }
    $out[] = '';
    $out[] = '### 管理员高危码';
    $out[] = '';
    foreach ($admin as $code) {
        $out[] = '- `' . $code . '`';
    }
    $out[] = '';
    $out[] = '## 错误码';
    $out[] = '';
    $out[] = '| 码 | HTTP | 说明 | 通用 |';
    $out[] = '| --- | --- | --- | --- |';
    foreach ($errors as $code => $entry) {
        $note = $entry['note'] !== '' ? '（' . oneLine($entry['note']) . '）' : '';
        $out[] = '| ' . $code . ' | ' . $entry['http_status']
            . ' | ' . oneLine($entry['summary']) . $note
            . ' | ' . ($entry['common'] ? '是' : '') . ' |';
    }
    $out[] = '';
    $out[] = '## MCP 工具表';
    $out[] = '';
    $out[] = '| 工具 | 档 | 权限码 | 绑定路由 | dev_only |';
    $out[] = '| --- | --- | --- | --- | --- |';
    foreach ($mcpTools as $tool) {
        $out[] = '| ' . $tool['name']
            . ' | ' . $tool['tier']
            . ' | `' . $tool['permission'] . '`'
            . ' | ' . ($tool['route'] === '' ? '—' : '`' . $tool['route'] . '`')
            . ' | ' . ($tool['dev_only'] ? '是' : '')
            . ' |';
    }
    $out[] = '';

    return implode("\n", $out);
}

/** 表格单元格不许含换行和竖线 */
function oneLine(string $text): string
{
    return str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], trim($text));
}

/** @param array{permission_kind: string, permission_codes: list<string>} $op */
function renderPermission(array $op): string
{
    return match ($op['permission_kind']) {
        'single' => '`' . $op['permission_codes'][0] . '`',
        'any_of' => implode(' \\| ', array_map(fn (string $c): string => '`' . $c . '`', $op['permission_codes'])),
        'all_of' => implode(' & ', array_map(fn (string $c): string => '`' . $c . '`', $op['permission_codes'])),
        default => $op['permission_kind'],
    };
}

/**
 * @param array<string, mixed> $doc
 * @param list<array{method: string, path: string, handler: string}> $routes
 * @param array<string, string> $registryCodes
 * @return list<string> 发现的不一致项，空表示全过
 */
function findConsistencyProblems(array $doc, array $routes, array $registryCodes): array
{
    $problems = [];

    $opRoutes = array_map(
        fn (array $op): string => $op['method'] . ' ' . $op['path'],
        $doc['operations'],
    );
    $genRoutes = array_map(
        fn (array $route): string => $route['method'] . ' ' . $route['path'],
        $routes,
    );
    $missing = array_diff($opRoutes, $genRoutes);
    $orphan = array_diff($genRoutes, $opRoutes);
    foreach ($missing as $item) {
        $problems[] = "路由表缺契约接口：{$item}";
    }
    foreach ($orphan as $item) {
        $problems[] = "路由表有契约外条目：{$item}";
    }

    $declared = array_values($registryCodes);
    foreach ($doc['operations'] as $op) {
        foreach ($op['permission_codes'] as $code) {
            if (!in_array($code, $declared, true)) {
                $problems[] = "权限码未进 PermissionRegistry：{$code}（{$op['operation_id']}）";
            }
        }
    }
    foreach (array_keys($doc['permissions']) as $code) {
        if (!in_array($code, $declared, true)) {
            $problems[] = "x-permissions 码未进 PermissionRegistry：{$code}";
        }
    }

    $catalogCodes = array_keys($doc['errors']);
    $registryErrorCodes = array_keys(ErrorCodeRegistry::HTTP_STATUS);
    foreach (array_diff($catalogCodes, $registryErrorCodes) as $code) {
        $problems[] = "错误码未进 ErrorCodeRegistry：{$code}";
    }
    foreach (array_diff($registryErrorCodes, $catalogCodes) as $code) {
        $problems[] = "ErrorCodeRegistry 有契约外码：{$code}";
    }

    $contractTools = array_column($doc['mcp_tools'], 'name');
    foreach ($doc['operations'] as $op) {
        if ($op['mcp'] !== null) {
            $contractTools[] = $op['mcp']['name'];
        }
    }
    $registryTools = array_column(McpToolRegistry::specs(), 'name');
    foreach (array_diff($contractTools, $registryTools) as $name) {
        $problems[] = "MCP 工具未进 McpToolRegistry：{$name}";
    }
    foreach (array_diff($registryTools, $contractTools) as $name) {
        $problems[] = "McpToolRegistry 有契约外工具：{$name}";
    }

    return $problems;
}

function isIgnoredPath(string $path, string $repoRoot): bool
{
    $resolved = realpath($path) ?: $path;
    $root = realpath($repoRoot);
    if ($root === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
        return true;
    }
    $relative = substr($resolved, strlen($root) + 1);
    exec('git -C ' . escapeshellarg($root) . ' check-ignore -q ' . escapeshellarg($relative), result_code: $code);

    return $code === 0;
}

/** @param list<string> $argv */
function main(array $argv): int
{
    $repoRoot = dirname(__DIR__);
    $outFile = $repoRoot . '/dist/ai-context-pack.md';
    $maxBytes = CONTEXT_PACK_MAX_BYTES;
    $selfCheck = false;

    $args = $argv;
    array_shift($args);
    for ($i = 0; $i < count($args); $i++) {
        switch ($args[$i]) {
            case '--out':
                $outFile = $args[++$i] ?? '';
                break;
            case '--max-bytes':
                $maxBytes = (int) ($args[++$i] ?? 0);
                break;
            case '--self-check':
                $selfCheck = true;
                break;
            case '--help':
            case '-h':
                echo "用法：php tools/ai-context-pack.php [--out <path>] [--max-bytes <n>] [--self-check]\n";
                return 0;
            default:
                fwrite(STDERR, "未知参数：{$args[$i]}\n");
                return 2;
        }
    }
    if ($outFile === '' || $maxBytes <= 0) {
        fwrite(STDERR, "--out 不能为空、--max-bytes 必须是正整数\n");
        return 2;
    }

    $contractFile = $repoRoot . '/contract/openapi.yaml';
    $doc = ContractDoc::parse($contractFile, 'host');
    $problems = $doc['problems'];
    // 与 contract-build 同一口径的源哈希：产物头部记的就是这个值
    $contractHash = SourceHash::compute(SourceHash::sourcesOf($repoRoot, $repoRoot . '/server'));

    foreach ([
        'server/config/route.generated.php',
        'server/config/plugin-routes.generated.json',
        'server/src/Contract/Generated/ErrorCodeRegistry.php',
        'server/src/Contract/Generated/McpToolRegistry.php',
        'server/src/Contract/Generated/PermissionRegistry.php',
        'web/src/api/v1/contract.generated.ts',
    ] as $generated) {
        $body = @file_get_contents($repoRoot . '/' . $generated);
        $recorded = $body === false ? null : SourceHash::parseHeader($body);
        if ($recorded !== $contractHash) {
            $problems[] = "{$generated} 记录的源哈希与契约重算值不一致（产物过期或被手改）";
        }
    }

    $routes = readGeneratedRoutes($repoRoot . '/server/config/route.generated.php');
    $problems = array_merge(
        $problems,
        findConsistencyProblems($doc, $routes, readPermissionCodes()),
    );

    $pack = renderContextPack($doc, $routes, (string) $contractHash);
    $size = strlen($pack);
    if ($size > $maxBytes) {
        $problems[] = "摘要包 {$size} 字节超过上限 {$maxBytes}（可加 --max-bytes 或收缩内容）";
    }

    if ($selfCheck) {
        foreach ($problems as $problem) {
            echo "FAIL {$problem}\n";
        }
        echo $problems === []
            ? "ai-context-pack 自检通过：接口 " . count($doc['operations'])
                . "、路由 " . count($routes)
                . "、错误码 " . count($doc['errors'])
                . "、MCP 工具 " . count($doc['mcp_tools'])
                . "、摘要 {$size} 字节（上限 {$maxBytes}）\n"
            : 'ai-context-pack 自检失败：' . count($problems) . " 项不一致\n";

        return $problems === [] ? 0 : 1;
    }

    if ($problems !== []) {
        fwrite(STDERR, "契约与生成产物不一致，先跑 php tools/contract-build 重建：\n");
        foreach ($problems as $problem) {
            fwrite(STDERR, "  - {$problem}\n");
        }
        return 1;
    }

    $dir = dirname($outFile);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "无法创建输出目录 {$dir}\n");
        return 1;
    }
    if (file_put_contents($outFile, $pack) === false) {
        fwrite(STDERR, "无法写入 {$outFile}\n");
        return 1;
    }
    if (!isIgnoredPath($outFile, $repoRoot)) {
        fwrite(STDERR, "警告：{$outFile} 在仓库内且未被 .gitignore 覆盖，注意别提交\n");
    }
    echo "已生成 {$outFile}（{$size} 字节，上限 {$maxBytes}）\n";

    return 0;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(main($_SERVER['argv']));
}
