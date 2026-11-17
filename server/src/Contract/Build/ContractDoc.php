<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\Build;

use Symfony\Component\Yaml\Yaml;

/**
 * 单份契约文件（宿主 openapi.yaml 或插件片段）的解析与归一化。
 * 只负责把 YAML 结构收成统一形状；跨文件冲突在 ConflictCheck。
 *
 * @phpstan-type Operation array{
 *   method: string,
 *   path: string,
 *   operation_id: string,
 *   summary: string,
 *   description: string,
 *   tags: list<string>,
 *   handler: string,
 *   permission_kind: string,
 *   permission_codes: list<string>,
 *   error_codes: list<string>,
 *   mcp: ?McpSpec,
 *   parameters: list<ParameterSpec>,
 *   source: string,
 * }
 * @phpstan-type McpSpec array{
 *   name: string, tier: string, title: string, description: string,
 *   permission: string, route: string, dev_only: bool,
 *   input: array<string, mixed>,
 * }
 * @phpstan-type ParameterSpec array{
 *   name: string, in: string, required: bool, schema: array<string, mixed>,
 * }
 * @phpstan-type ErrorEntry array{http_status: int, summary: string, common: bool, note: string}
 * @phpstan-type TreeNode array{
 *   group_key: string, group_title: string, menu_key: string, title: string,
 *   actions: list<array{action: string, label: string}>,
 * }
 * @phpstan-type SidebarMenu array{key: string, title: string, icon: string, path: string}
 * @phpstan-type ParsedDoc array{
 *   operations: list<Operation>,
 *   errors: array<string, ErrorEntry>,
 *   permissions: array<string, string>,
 *   tree: list<TreeNode>,
 *   sidebar: list<SidebarMenu>,
 *   admin: list<string>,
 *   mcp_tools: list<McpSpec>,
 *   problems: list<string>,
 * }
 */
final class ContractDoc
{
    private const PERMISSION_PATTERN = '/^[a-z][a-z0-9_]*(:[a-z0-9][a-z0-9_-]*)+$/';
    private const RESERVED_PERMISSIONS = ['public', 'authenticated', 'super'];
    private const MCP_TIERS = ['read', 'write', 'high_risk'];
    private const TOOL_NAME_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';
    private const ROUTE_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];
    private const V1_PREFIX = '/api/admin/v1';

    /**
     * @param string $source 来源标签：'host' 或插件 app 名（如 sand-foo）
     * @return ParsedDoc
     */
    public static function parse(string $file, string $source): array
    {
        $problems = [];
        if (!is_file($file)) {
            $problems[] = "{$source}: 契约文件不存在 {$file}";
            return self::emptyDoc($problems);
        }
        try {
            $raw = Yaml::parseFile($file);
        } catch (\Throwable $error) {
            $problems[] = "{$source}: YAML 解析失败 " . $error->getMessage();
            return self::emptyDoc($problems);
        }
        if (!is_array($raw)) {
            $problems[] = "{$source}: 契约根节点不是 map";
            return self::emptyDoc($problems);
        }

        $errors = self::parseCatalog($raw['x-error-catalog'] ?? [], $source, $problems);
        $permissions = self::parseExtraPermissions($raw['x-permissions'] ?? [], $source, $problems);
        $tree = self::parseTree($raw['x-permission-tree'] ?? [], $source, $problems);
        $sidebar = self::parseSidebar($raw['x-sidebar-menus'] ?? [], $source, $problems);
        $admin = self::parseAdmin($raw['x-admin-permissions'] ?? [], $source, $problems);
        $operations = self::parsePaths($raw['paths'] ?? [], $source, $problems);
        $mcpTools = self::parseMcpTools($raw['x-mcp-tools'] ?? [], $source, $problems);

        return [
            'operations' => $operations,
            'errors' => $errors,
            'permissions' => $permissions,
            'tree' => $tree,
            'sidebar' => $sidebar,
            'admin' => $admin,
            'mcp_tools' => $mcpTools,
            'problems' => $problems,
        ];
    }

    /**
     * @param list<string> $problems
     * @return ParsedDoc
     */
    private static function emptyDoc(array $problems): array
    {
        return [
            'operations' => [], 'errors' => [], 'permissions' => [],
            'tree' => [], 'sidebar' => [], 'admin' => [], 'mcp_tools' => [],
            'problems' => $problems,
        ];
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return array<string, ErrorEntry>
     */
    private static function parseCatalog(mixed $raw, string $source, array &$problems): array
    {
        $catalog = [];
        if (!is_array($raw)) {
            $problems[] = "{$source}: x-error-catalog 必须是 map";
            return $catalog;
        }
        foreach ($raw as $code => $entry) {
            $errorCode = (string) $code;
            if (!is_array($entry)) {
                $problems[] = "{$source}: 错误码 {$errorCode} 的条目不是 map";
                continue;
            }
            $status = $entry['http_status'] ?? null;
            if (!is_int($status) || $status < 400 || $status > 599) {
                $problems[] = "{$source}: 错误码 {$errorCode} 的 http_status 不是 4xx/5xx 整数";
                continue;
            }
            $summary = $entry['summary'] ?? '';
            if (!is_string($summary) || $summary === '') {
                $problems[] = "{$source}: 错误码 {$errorCode} 缺 summary";
                continue;
            }
            $catalog[$errorCode] = [
                'http_status' => $status,
                'summary' => $summary,
                'common' => ($entry['common'] ?? false) === true,
                'note' => is_string($entry['note'] ?? null) ? (string) $entry['note'] : '',
            ];
        }

        return $catalog;
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return array<string, string>
     */
    private static function parseExtraPermissions(mixed $raw, string $source, array &$problems): array
    {
        $map = [];
        if (!is_array($raw)) {
            $problems[] = "{$source}: x-permissions 必须是 list";
            return $map;
        }
        foreach ($raw as $index => $item) {
            if (!is_array($item) || !is_string($item['code'] ?? null)) {
                $problems[] = "{$source}: x-permissions[{$index}] 缺 code";
                continue;
            }
            $code = (string) $item['code'];
            if (!self::isPermissionCode($code)) {
                $problems[] = "{$source}: 权限码 {$code} 形状不合法";
                continue;
            }
            $map[$code] = is_string($item['title'] ?? null) ? (string) $item['title'] : $code;
        }

        return $map;
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return list<TreeNode>
     */
    private static function parseTree(mixed $raw, string $source, array &$problems): array
    {
        $tree = [];
        if (!is_array($raw)) {
            $problems[] = "{$source}: x-permission-tree 必须是 list";
            return $tree;
        }
        foreach ($raw as $index => $node) {
            if (!is_array($node)) {
                $problems[] = "{$source}: x-permission-tree[{$index}] 不是 map";
                continue;
            }
            $menuKey = $node['menu_key'] ?? '';
            $actions = $node['actions'] ?? [];
            if (!is_string($menuKey) || !is_array($actions)) {
                $problems[] = "{$source}: x-permission-tree[{$index}] 缺 menu_key/actions";
                continue;
            }
            $actionRows = [];
            foreach ($actions as $action) {
                if (!is_array($action) || !is_string($action['action'] ?? null) || !is_string($action['label'] ?? null)) {
                    $problems[] = "{$source}: {$menuKey} 的 actions 行缺 action/label";
                    continue;
                }
                $actionRows[] = ['action' => (string) $action['action'], 'label' => (string) $action['label']];
            }
            $tree[] = [
                'group_key' => is_string($node['group_key'] ?? null) ? (string) $node['group_key'] : 'misc',
                'group_title' => is_string($node['group_title'] ?? null) ? (string) $node['group_title'] : '',
                'menu_key' => $menuKey,
                'title' => is_string($node['title'] ?? null) ? (string) $node['title'] : $menuKey,
                'actions' => $actionRows,
            ];
        }

        return $tree;
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return list<SidebarMenu>
     */
    private static function parseSidebar(mixed $raw, string $source, array &$problems): array
    {
        $menus = [];
        if (!is_array($raw)) {
            $problems[] = "{$source}: x-sidebar-menus 必须是 list";
            return $menus;
        }
        foreach ($raw as $index => $item) {
            if (!is_array($item) || !is_string($item['key'] ?? null) || !is_string($item['title'] ?? null)) {
                $problems[] = "{$source}: x-sidebar-menus[{$index}] 缺 key/title";
                continue;
            }
            $menus[] = [
                'key' => (string) $item['key'],
                'title' => (string) $item['title'],
                'icon' => is_string($item['icon'] ?? null) ? (string) $item['icon'] : '',
                'path' => is_string($item['path'] ?? null) ? (string) $item['path'] : '',
            ];
        }

        return $menus;
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return list<string>
     */
    private static function parseAdmin(mixed $raw, string $source, array &$problems): array
    {
        $codes = [];
        if (!is_array($raw)) {
            $problems[] = "{$source}: x-admin-permissions 必须是 list";
            return $codes;
        }
        foreach ($raw as $code) {
            if (!is_string($code) || !self::isPermissionCode($code)) {
                $problems[] = "{$source}: x-admin-permissions 含非法码";
                continue;
            }
            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return list<Operation>
     */
    private static function parsePaths(mixed $raw, string $source, array &$problems): array
    {
        $operations = [];
        if (!is_array($raw)) {
            $problems[] = "{$source}: paths 必须是 map";
            return $operations;
        }
        foreach ($raw as $path => $item) {
            $routePath = (string) $path;
            if (!str_starts_with($routePath, self::V1_PREFIX)) {
                $problems[] = "{$source}: 路径 {$routePath} 不在 " . self::V1_PREFIX . ' 前缀下';
                continue;
            }
            if ($source !== 'host') {
                $pluginPrefix = self::V1_PREFIX . '/plugin/' . $source . '/';
                if (!str_starts_with($routePath, $pluginPrefix)) {
                    $problems[] = "{$source}: 路径 {$routePath} 必须落在 {$pluginPrefix} 命名空间下";
                    continue;
                }
            }
            if (!is_array($item)) {
                $problems[] = "{$source}: {$routePath} 的 path item 不是 map";
                continue;
            }
            foreach ($item as $method => $op) {
                $httpMethod = strtolower((string) $method);
                if (!in_array($httpMethod, self::ROUTE_METHODS, true)) {
                    $problems[] = "{$source}: {$routePath} 的 {$httpMethod} 不是 HTTP 方法";
                    continue;
                }
                if (!is_array($op)) {
                    $problems[] = "{$source}: {$httpMethod} {$routePath} 的 operation 不是 map";
                    continue;
                }
                $operations[] = self::parseOperation($op, $httpMethod, $routePath, $source, $problems);
            }
        }

        return $operations;
    }

    /**
     * @param array<mixed, mixed> $op
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return Operation
     */
    private static function parseOperation(array $op, string $method, string $path, string $source, array &$problems): array
    {
        $label = strtoupper($method) . ' ' . $path;
        $operationId = $op['operationId'] ?? '';
        if (!is_string($operationId) || $operationId === '') {
            $problems[] = "{$source}: {$label} 缺 operationId";
            $operationId = '';
        }
        $handler = $op['x-handler'] ?? '';
        if (!is_string($handler)) {
            $handler = '';
        }
        if ($handler === '') {
            $problems[] = "{$source}: {$label} 缺 x-handler";
        } elseif (!preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/', $handler)) {
            $problems[] = "{$source}: {$label} 的 x-handler「{$handler}」不是 Class::method 形状";
        }

        [$kind, $codes] = self::parsePermission($op['x-permission'] ?? null, $source, $label, $problems);

        $errorCodes = [];
        $rawCodes = $op['x-error-codes'] ?? [];
        if (!is_array($rawCodes)) {
            $problems[] = "{$source}: {$label} 的 x-error-codes 必须是 list";
        } else {
            foreach ($rawCodes as $code) {
                if (is_string($code)) {
                    $errorCodes[] = $code;
                } else {
                    $problems[] = "{$source}: {$label} 的 x-error-codes 含非字符串项";
                }
            }
        }

        $mcp = null;
        if (isset($op['x-mcp'])) {
            $mcp = self::parseOpMcp($op['x-mcp'], $kind, $codes, $operationId, $path, $source, $label, $problems);
        }

        $parameters = [];
        $rawParams = $op['parameters'] ?? [];
        if (is_array($rawParams)) {
            foreach ($rawParams as $param) {
                if (!is_array($param) || !is_string($param['name'] ?? null) || !is_string($param['in'] ?? null)) {
                    $problems[] = "{$source}: {$label} 的 parameters 行缺 name/in";
                    continue;
                }
                $parameters[] = [
                    'name' => (string) $param['name'],
                    'in' => (string) $param['in'],
                    'required' => ($param['required'] ?? false) === true,
                    'schema' => self::schemaOf($param['schema'] ?? null),
                ];
            }
        }

        return [
            'method' => strtoupper($method),
            'path' => $path,
            'operation_id' => $operationId,
            'summary' => is_string($op['summary'] ?? null) ? (string) $op['summary'] : '',
            'description' => is_string($op['description'] ?? null) ? (string) $op['description'] : '',
            'tags' => self::stringList($op['tags'] ?? []),
            'handler' => $handler,
            'permission_kind' => $kind,
            'permission_codes' => $codes,
            'error_codes' => $errorCodes,
            'mcp' => $mcp,
            'parameters' => $parameters,
            'source' => $source,
        ];
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return array{0: string, 1: list<string>} [kind, codes]
     */
    private static function parsePermission(mixed $raw, string $source, string $label, array &$problems): array
    {
        if (is_string($raw)) {
            if (in_array($raw, self::RESERVED_PERMISSIONS, true)) {
                return [$raw, []];
            }
            if (self::isPermissionCode($raw)) {
                return ['single', [$raw]];
            }
            $problems[] = "{$source}: {$label} 的 x-permission「{$raw}」形状不合法";
            return ['invalid', []];
        }
        if (is_array($raw)) {
            $keys = array_keys($raw);
            $kind = in_array('any_of', $keys, true) ? 'any_of' : (in_array('all_of', $keys, true) ? 'all_of' : '');
            if ($kind === '' || !is_array($raw[$kind])) {
                $problems[] = "{$source}: {$label} 的 x-permission 复合形态只许 any_of/all_of";
                return ['invalid', []];
            }
            $codes = [];
            foreach ($raw[$kind] as $code) {
                if (!is_string($code) || !self::isPermissionCode($code)) {
                    $problems[] = "{$source}: {$label} 的 {$kind} 含非法码";
                    continue;
                }
                $codes[] = $code;
            }
            if (count($codes) < 2) {
                $problems[] = "{$source}: {$label} 的 {$kind} 少于两个码";
            }
            return [$kind, $codes];
        }
        $problems[] = "{$source}: {$label} 缺 x-permission";
        return ['invalid', []];
    }

    /**
     * @param list<string> $codes
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return McpSpec
     */
    private static function parseOpMcp(
        mixed $raw,
        string $kind,
        array $codes,
        string $operationId,
        string $path,
        string $source,
        string $label,
        array &$problems,
    ): array {
        if (!is_array($raw)) {
            $problems[] = "{$source}: {$label} 的 x-mcp 必须是 map";
            $raw = [];
        }
        $tier = $raw['tier'] ?? '';
        if (!is_string($tier) || !in_array($tier, self::MCP_TIERS, true)) {
            $problems[] = "{$source}: {$label} 的 x-mcp.tier 必须是 read/write/high_risk";
            $tier = 'read';
        }
        $name = $raw['name'] ?? '';
        if (!is_string($name) || !preg_match(self::TOOL_NAME_PATTERN, $name)) {
            $problems[] = "{$source}: {$label} 的 x-mcp.name 缺失或形状不合法";
            $name = '';
        }
        $permission = $raw['permission'] ?? '';
        if (!is_string($permission) || $permission === '') {
            if ($kind === 'single' && $codes !== []) {
                $permission = $codes[0];
            } else {
                $problems[] = "{$source}: {$label} 的 x-mcp.permission 缺失且接口权限不是单码，无法派生";
                $permission = '';
            }
        } elseif (!self::isPermissionCode($permission)) {
            $problems[] = "{$source}: {$label} 的 x-mcp.permission「{$permission}」形状不合法";
        }
        $input = self::schemaOf($raw['input'] ?? null);
        if ($input === []) {
            $input = ['type' => 'object', 'properties' => []];
        }
        $input = self::injectIdempotency($input, $tier, $source, $label, $problems);

        return [
            'name' => $name,
            'tier' => $tier,
            'title' => is_string($raw['title'] ?? null) ? (string) $raw['title'] : $operationId,
            'description' => is_string($raw['description'] ?? null) ? (string) $raw['description'] : '',
            'permission' => $permission,
            'route' => $path,
            'dev_only' => false,
            'input' => $input,
        ];
    }

    /**
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return list<McpSpec>
     */
    private static function parseMcpTools(mixed $raw, string $source, array &$problems): array
    {
        $tools = [];
        if (!is_array($raw)) {
            $problems[] = "{$source}: x-mcp-tools 必须是 list";
            return $tools;
        }
        foreach ($raw as $index => $item) {
            if (!is_array($item)) {
                $problems[] = "{$source}: x-mcp-tools[{$index}] 不是 map";
                continue;
            }
            $name = $item['name'] ?? '';
            if (!is_string($name) || !preg_match(self::TOOL_NAME_PATTERN, $name)) {
                $problems[] = "{$source}: x-mcp-tools[{$index}] name 缺失或形状不合法";
                continue;
            }
            $tier = $item['tier'] ?? '';
            if (!is_string($tier) || !in_array($tier, self::MCP_TIERS, true)) {
                $problems[] = "{$source}: 工具 {$name} 的 tier 必须是 read/write/high_risk";
                continue;
            }
            $permission = $item['permission'] ?? '';
            if (!is_string($permission) || !self::isPermissionCode($permission)) {
                $problems[] = "{$source}: 工具 {$name} 的 permission 缺失或形状不合法";
                continue;
            }
            $devOnly = ($item['dev_only'] ?? false) === true;
            if (!$devOnly) {
                // 顶层独立工具不绑接口，只有 dev 链有存在理由；生产面工具必须挂 operation。
                $problems[] = "{$source}: 独立工具 {$name} 必须声明 dev_only: true";
            }
            $input = self::schemaOf($item['input'] ?? null);
            if ($input === []) {
                $input = ['type' => 'object', 'properties' => []];
            }
            $input = self::injectIdempotency($input, $tier, $source, $name, $problems);
            $tools[] = [
                'name' => $name,
                'tier' => $tier,
                'title' => is_string($item['title'] ?? null) ? (string) $item['title'] : $name,
                'description' => is_string($item['description'] ?? null) ? (string) $item['description'] : '',
                'permission' => $permission,
                'route' => '',
                'dev_only' => $devOnly,
                'input' => $input,
            ];
        }

        return $tools;
    }

    /**
     * write/high_risk 档自动注入 idempotency_key（必填）与 dry_run（可选）；
     * 作者手写同名字段但形状不一致算冲突，一致则原样保留。
     * @param array<string, mixed> $input
     * @param list<string> $problems
     * @param-out list<string> $problems
     * @return array<string, mixed>
     */
    private static function injectIdempotency(array $input, string $tier, string $source, string $label, array &$problems): array
    {
        if ($tier === 'read') {
            return $input;
        }
        // 注入形状与 DevCatalog 既有手写 schema 逐键一致，保证线报文不变。
        $keySpec = ['type' => 'string'];
        $drySpec = ['type' => 'boolean'];
        $input['type'] = 'object';
        $properties = is_array($input['properties'] ?? null) ? $input['properties'] : [];
        if (isset($properties['idempotency_key']) && $properties['idempotency_key'] !== $keySpec) {
            $problems[] = "{$source}: {$label} 手写 idempotency_key 与注入形状不一致";
        }
        $properties['idempotency_key'] = $keySpec;
        if (isset($properties['dry_run']) && $properties['dry_run'] !== $drySpec) {
            $problems[] = "{$source}: {$label} 手写 dry_run 与注入形状不一致";
        }
        $properties['dry_run'] = $drySpec;
        $input['properties'] = $properties;
        $required = is_array($input['required'] ?? null) ? array_values(array_filter($input['required'], 'is_string')) : [];
        if (!in_array('idempotency_key', $required, true)) {
            $required[] = 'idempotency_key';
        }
        $input['required'] = $required;

        return $input;
    }

    /**
     * YAML 解析出的 map 键宽化为 mixed；schema 语义上只收字符串键。
     * @return array<string, mixed>
     */
    private static function schemaOf(mixed $value): array
    {
        $normalized = [];
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key)) {
                    $normalized[$key] = $item;
                }
            }
        }

        return $normalized;
    }

    /** @return list<string> */
    private static function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $list = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    public static function isPermissionCode(string $code): bool
    {
        return preg_match(self::PERMISSION_PATTERN, $code) === 1;
    }
}
