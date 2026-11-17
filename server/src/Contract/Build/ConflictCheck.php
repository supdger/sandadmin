<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\Build;

/**
 * 跨文件冲突检查。收集期把宿主与插件片段合并后跑这七条，
 * 任一命中都记为冲突并中止构建：
 *   1. path + method 撞；
 *   2. operationId 撞；
 *   3. 权限码域违规（插件只许 sand_<词干>: 域）；
 *   4. MCP 工具名撞（插件工具必须 sand_<词干>_ 前缀）；
 *   5. 错误码重复声明（插件只许声明本域新增码）；
 *   6. x-error-codes 引用未登记的错误码；
 *   7. public/authenticated 接口挂 write/high_risk 档 MCP 工具。
 *
 * @phpstan-import-type Operation from ContractDoc
 * @phpstan-import-type McpSpec from ContractDoc
 * @phpstan-import-type ErrorEntry from ContractDoc
 * @phpstan-import-type ParsedDoc from ContractDoc
 */
final class ConflictCheck
{
    /**
     * @param array<string, ParsedDoc> $docs 来源标签 => 解析结果
     * @param array<string, string> $pluginStems 插件 app => 词干（sand-foo => foo）
     * @return list<string> 冲突描述，空数组表示零冲突
     */
    public static function check(array $docs, array $pluginStems): array
    {
        $conflicts = [];

        $routeOwners = [];
        $operationOwners = [];
        $toolOwners = [];
        $errorOwners = [];
        /** @var array<string, ErrorEntry> $catalog */
        $catalog = [];
        /** @var list<array{0: string, 1: string}> $refs */
        $refs = [];

        foreach ($docs as $source => $doc) {
            $stem = $pluginStems[$source] ?? null;

            foreach ($doc['operations'] as $op) {
                $routeKey = $op['method'] . ' ' . $op['path'];
                if (isset($routeOwners[$routeKey])) {
                    $conflicts[] = "路由 {$routeKey} 冲突：{$routeOwners[$routeKey]} 与 {$source} 声明重复";
                } else {
                    $routeOwners[$routeKey] = $source;
                }
                if ($op['operation_id'] !== '') {
                    if (isset($operationOwners[$op['operation_id']])) {
                        $conflicts[] = "operationId {$op['operation_id']} 冲突：{$operationOwners[$op['operation_id']]} 与 {$source} 声明重复";
                    } else {
                        $operationOwners[$op['operation_id']] = $source;
                    }
                }
                if ($stem !== null) {
                    foreach ($op['permission_codes'] as $code) {
                        if (!str_starts_with($code, "sand_{$stem}:")) {
                            $conflicts[] = "权限码 {$code} 冲突：插件 {$source} 只许声明 sand_{$stem}: 域";
                        }
                    }
                }
                if ($op['mcp'] !== null && in_array($op['mcp']['tier'], ['write', 'high_risk'], true)
                    && in_array($op['permission_kind'], ['public', 'authenticated'], true)) {
                    $conflicts[] = "MCP 档位冲突：{$routeKey} 是 {$op['permission_kind']} 接口，不能挂 {$op['mcp']['tier']} 档工具";
                }
                foreach ($op['error_codes'] as $code) {
                    // 未登记引用在第六步统一判；先收集引用
                    $refs[] = [$code, "{$source} {$routeKey}"];
                }
            }

            if ($stem !== null) {
                foreach ($doc['permissions'] as $code => $title) {
                    if (!str_starts_with($code, "sand_{$stem}:")) {
                        $conflicts[] = "权限码 {$code} 冲突：插件 {$source} 只许声明 sand_{$stem}: 域";
                    }
                }
            }

            foreach ($doc['mcp_tools'] as $tool) {
                if (isset($toolOwners[$tool['name']])) {
                    $conflicts[] = "MCP 工具名 {$tool['name']} 冲突：{$toolOwners[$tool['name']]} 与 {$source} 声明重复";
                } else {
                    $toolOwners[$tool['name']] = $source;
                }
                if ($stem !== null && !str_starts_with($tool['name'], "sand_{$stem}_")) {
                    $conflicts[] = "MCP 工具名 {$tool['name']} 冲突：插件 {$source} 的工具必须带 sand_{$stem}_ 前缀";
                }
                if ($stem !== null && !str_starts_with($tool['permission'], "sand_{$stem}:")) {
                    $conflicts[] = "权限码 {$tool['permission']} 冲突：插件 {$source} 只许声明 sand_{$stem}: 域";
                }
            }
            foreach ($doc['operations'] as $op) {
                if ($op['mcp'] === null) {
                    continue;
                }
                $tool = $op['mcp'];
                if ($tool['name'] !== '') {
                    if (isset($toolOwners[$tool['name']])) {
                        $conflicts[] = "MCP 工具名 {$tool['name']} 冲突：{$toolOwners[$tool['name']]} 与 {$source} 声明重复";
                    } else {
                        $toolOwners[$tool['name']] = $source;
                    }
                    if ($stem !== null && !str_starts_with($tool['name'], "sand_{$stem}_")) {
                        $conflicts[] = "MCP 工具名 {$tool['name']} 冲突：插件 {$source} 的工具必须带 sand_{$stem}_ 前缀";
                    }
                }
            }

            foreach ($doc['errors'] as $code => $entry) {
                if (isset($errorOwners[$code])) {
                    $conflicts[] = "错误码 {$code} 冲突：{$errorOwners[$code]} 已声明，{$source} 重复声明";
                } else {
                    $errorOwners[$code] = $source;
                    $catalog[$code] = $entry;
                }
            }
        }

        foreach ($refs as [$code, $where]) {
            if (!isset($catalog[$code])) {
                $conflicts[] = "错误码 {$code} 未登记：{$where} 的 x-error-codes 引用了不在 x-error-catalog 的码";
            }
        }

        return $conflicts;
    }

    /**
     * 合并各来源的权限码全集（路由码 + 顶层声明 + 权限树动作码 + 工具码）。
     * @param array<string, ParsedDoc> $docs
     * @return array<string, string> code => title
     */
    public static function collectPermissions(array $docs): array
    {
        $codes = [];
        foreach ($docs as $doc) {
            foreach ($doc['permissions'] as $code => $title) {
                $codes[$code] = $title;
            }
            foreach ($doc['tree'] as $node) {
                foreach ($node['actions'] as $action) {
                    $code = $node['menu_key'] . ':' . $action['action'];
                    $codes[$code] = $codes[$code] ?? ($node['title'] . '-' . $action['label']);
                }
            }
            foreach ($doc['operations'] as $op) {
                foreach ($op['permission_codes'] as $code) {
                    $codes[$code] = $codes[$code] ?? $code;
                }
            }
            foreach ($doc['mcp_tools'] as $tool) {
                if ($tool['permission'] !== '') {
                    $codes[$tool['permission']] = $codes[$tool['permission']] ?? $tool['title'];
                }
            }
        }
        ksort($codes);

        return $codes;
    }

    /**
     * @param array<string, ParsedDoc> $docs
     * @return array<string, ErrorEntry>
     */
    public static function collectErrors(array $docs): array
    {
        $catalog = [];
        foreach ($docs as $doc) {
            foreach ($doc['errors'] as $code => $entry) {
                $catalog[$code] = $entry;
            }
        }
        ksort($catalog);

        return $catalog;
    }

    /**
     * @param array<string, ParsedDoc> $docs
     * @return list<McpSpec> 按工具名排序
     */
    public static function collectTools(array $docs): array
    {
        $tools = [];
        foreach ($docs as $doc) {
            foreach ($doc['mcp_tools'] as $tool) {
                $tools[$tool['name']] = $tool;
            }
            foreach ($doc['operations'] as $op) {
                if ($op['mcp'] !== null && $op['mcp']['name'] !== '') {
                    $tools[$op['mcp']['name']] = $op['mcp'];
                }
            }
        }
        ksort($tools);

        return array_values($tools);
    }
}
