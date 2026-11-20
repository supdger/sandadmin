<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\Build;

use Sandadmin\Contract\SourceHash;

/**
 * PHP 侧产物发射：路由表、插件路由清单、三张注册表、插件自身路由表、
 * 测试骨架。所有产物头部统一带 <contract-generated> 标记与源哈希，
 * --check 逐字节比对，人改产物会被门禁打回。
 *
 * @phpstan-import-type Operation from ContractDoc
 * @phpstan-import-type McpSpec from ContractDoc
 * @phpstan-import-type ErrorEntry from ContractDoc
 * @phpstan-import-type ParsedDoc from ContractDoc
 * @phpstan-import-type TreeNode from ContractDoc
 * @phpstan-import-type SidebarMenu from ContractDoc
 */
final class PhpEmit
{
    // J-4 写死值：宿主每进程连接预算 = DBAL 主从 + think-orm 主从
    private const HOST_PER_PROCESS = 4;

    /**
     * 连接预算清单（JSON）：J-4 公式「进程数 ×（宿主连接数 + 命名连接数）」的
     * 每进程侧输入。插件声明 x-connections 变化时本文件漂移，容量评审按
     * plugin_per_process 重算连接账，门禁逐字节比对防漏跑收集。
     * @param list<array{plugin: string, name: string, connections_per_process: int}> $connections
     * @param list<string> $sourceLabels
     */
    public static function emitConnectionBudget(array $connections, array $sourceLabels, string $sourceHash): string
    {
        $pluginPerProcess = 0;
        foreach ($connections as $conn) {
            $pluginPerProcess += $conn['connections_per_process'];
        }
        $manifest = [
            '_comment' => SourceHash::headerLines($sourceLabels, $sourceHash),
            'formula' => 'total = processes × (host_per_process + plugin_per_process)',
            'host_per_process' => self::HOST_PER_PROCESS,
            'plugin_per_process' => $pluginPerProcess,
            'total_per_process' => self::HOST_PER_PROCESS + $pluginPerProcess,
            'connections' => $connections,
        ];
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('连接预算清单 JSON 编码失败');
        }

        return $encoded . "\n";
    }

    /**
     * 宿主路由表：只收 host 来源的 operation，按契约文档顺序发射。
     * 注册顺序即契约顺序（path 书写顺序、path 内方法书写顺序）。
     * @param list<Operation> $operations
     * @param list<string> $sourceLabels
     */
    public static function emitRoutes(array $operations, array $sourceLabels, string $sourceHash): string
    {
        $uses = [];
        foreach ($operations as $op) {
            if ($op['handler'] === '') {
                continue;
            }
            [$class] = explode('::', $op['handler'], 2);
            $uses[$class] = true;
        }
        $classes = array_keys($uses);
        sort($classes);

        $lines = ['<?php', '', 'declare(strict_types=1);', ''];
        foreach (SourceHash::headerLines($sourceLabels, $sourceHash) as $line) {
            $lines[] = '// ' . $line;
        }
        $lines[] = '';
        foreach ($classes as $class) {
            $lines[] = 'use ' . $class . ';';
        }
        $lines[] = 'use Webman\Route;';
        $lines[] = '';
        $lines[] = '// Config::loadFromDir 会按 *.php 误包本表（只排除基名 route.php）：';
        $lines[] = '// 那时无 FastRoute collector，直接注册会致命。装载标记只由';
        $lines[] = '// config/route.php 在 Route::load 回调内定义，缺席即返回空数组、';
        $lines[] = '// 无害并入配置树。';
        $lines[] = "if (!defined('SAND_CONTRACT_ROUTE_LOAD')) {";
        $lines[] = '    return [];';
        $lines[] = '}';
        $lines[] = '';
        foreach ($operations as $op) {
            [$class, $method] = explode('::', $op['handler'], 2);
            $basename = self::classBasename($class);
            $verb = strtolower($op['method']);
            $lines[] = "Route::{$verb}('{$op['path']}', [{$basename}::class, '{$method}']);";
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * 插件路由入口清单（JSON）：bootstrap 与启动守卫 json_decode 读 routes 字段，
     * 不再扫 plugin/ 全目录。条目是 Route::load 实际加载的入口文件
     * （相对 server base_path）；契约插件的入口是生成的薄壳 route.php。
     * 清单刻意用 JSON 而不是 PHP return 形态：装载点 require 变量路径
     * 会被 TypePHP 门禁判动态加载，纯数据文件不需要任何豁免。
     * _comment 逐行嵌入与其他产物相同的头行，parseHeader 的正则照样命中。
     * @param list<string> $entries
     * @param list<string> $sourceLabels
     */
    public static function emitPluginRoutes(array $entries, array $sourceLabels, string $sourceHash): string
    {
        $manifest = [
            '_comment' => SourceHash::headerLines($sourceLabels, $sourceHash),
            'routes' => $entries,
        ];
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('插件路由清单 JSON 编码失败');
        }

        return $encoded . "\n";
    }

    /**
     * 插件自身路由表（写进 <插件包>/config/route.generated.php）。
     * @param list<Operation> $operations 单插件的 operation
     * @param list<string> $sourceLabels
     */
    public static function emitPluginRouteTable(array $operations, array $sourceLabels, string $sourceHash): string
    {
        return self::emitRoutes($operations, $sourceLabels, $sourceHash);
    }

    /**
     * 插件路由薄壳：Route::load 只认 config/route.php，
     * 契约插件由这层薄壳转发到生成的路由表，并关掉本插件的
     * /app/<插件>/ 默认路由（webman 把路径里的连字符归一掉，
     * disableDefaultRoute 实参也只收 [a-zA-Z0-9_]，连字符会静默
     * 失效，所以一律发去连字符后的插件名）。
     * @param list<string> $sourceLabels
     */
    public static function emitPluginRouteShell(string $app, array $sourceLabels, string $sourceHash): string
    {
        $pluginName = str_replace('-', '', $app);
        $lines = ['<?php', '', 'declare(strict_types=1);', ''];
        foreach (SourceHash::headerLines($sourceLabels, $sourceHash) as $line) {
            $lines[] = '// ' . $line;
        }
        $lines[] = '';
        $lines[] = 'use Webman\Route;';
        $lines[] = '';
        $lines[] = "require __DIR__ . '/route.generated.php';";
        $lines[] = '';
        $lines[] = 'Route::disableDefaultRoute(' . var_export($pluginName, true) . ');';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $permissions code => title（已排序）
     * @param list<TreeNode> $tree
     * @param list<string> $adminCodes
     * @param list<SidebarMenu> $sidebar
     * @param list<string> $sourceLabels
     */
    public static function emitPermissionRegistry(
        array $permissions,
        array $tree,
        array $adminCodes,
        array $sidebar,
        array $sourceLabels,
        string $sourceHash,
    ): string {
        $consts = [];
        foreach (array_keys($permissions) as $code) {
            $name = self::permissionConst($code);
            $consts[] = "    public const {$name} = " . var_export($code, true) . ';';
        }
        $menuConsts = [];
        $menuKeys = [];
        foreach ($sidebar as $menu) {
            $name = self::menuConst($menu['key']);
            $menuConsts[] = "    public const {$name} = " . var_export($menu['key'], true) . ';';
            $menuKeys[] = $menu['key'];
        }

        $lines = ['<?php', '', 'declare(strict_types=1);', ''];
        foreach (SourceHash::headerLines($sourceLabels, $sourceHash) as $line) {
            $lines[] = '// ' . $line;
        }
        $lines[] = '';
        $lines[] = 'namespace Sandadmin\Contract\Generated;';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * 权限注册表：契约派生的权限码常量与展示树。';
        $lines[] = ' * 判定面一律引用这里的常量，不再散落字符串字面量。';
        $lines[] = ' */';
        $lines[] = 'final class PermissionRegistry';
        $lines[] = '{';
        $lines = array_merge($lines, $consts);
        if ($menuConsts !== []) {
            $lines[] = '';
            $lines = array_merge($lines, $menuConsts);
        }
        $lines[] = '';
        $lines[] = '    /** @var list<string> 契约声明的全部权限码 */';
        $lines[] = '    public const CODES = ' . self::inlineList(array_keys($permissions)) . ';';
        $lines[] = '';
        $lines[] = '    /** @var list<string> 管理员高危码（顶掉 UserService::ADMIN_CODES） */';
        $lines[] = '    public const ADMIN_CODES = ' . self::inlineList($adminCodes) . ';';
        $lines[] = '';
        $lines[] = '    /** @var list<string> 侧栏菜单键（顶掉 MenuService 手写键） */';
        $lines[] = '    public const MENU_KEYS = ' . self::inlineList($menuKeys) . ';';
        $lines[] = '';
        $lines[] = '    /**';
        $lines[] = '     * @var list<array{group_key: string, group_title: string, menu_key: string, title: string, actions: list<array{action: string, label: string}>}>';
        $lines[] = '     *      角色配置权限树（顶掉 RoleService::MENUS）';
        $lines[] = '     */';
        $lines[] = '    public const TREE = ' . self::exportValue($tree, 1) . ';';
        $lines[] = '';
        $lines[] = '    /** @var list<array{key: string, title: string, icon: string, path: string}> 侧栏菜单定义 */';
        $lines[] = '    public const SIDEBAR = ' . self::exportValue($sidebar, 1) . ';';
        $lines[] = '';
        $lines[] = '    /** @param list<string> $codes */';
        $lines[] = '    public static function allDeclared(array $codes): bool';
        $lines[] = '    {';
        $lines[] = "        foreach (\$codes as \$code) {";
        $lines[] = '            if (!in_array($code, self::CODES, true)) {';
        $lines[] = '                return false;';
        $lines[] = '            }';
        $lines[] = '        }';
        $lines[] = '';
        $lines[] = '        return true;';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, ErrorEntry> $catalog
     * @param list<string> $sourceLabels
     */
    public static function emitErrorRegistry(array $catalog, array $sourceLabels, string $sourceHash): string
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', ''];
        foreach (SourceHash::headerLines($sourceLabels, $sourceHash) as $line) {
            $lines[] = '// ' . $line;
        }
        $lines[] = '';
        $lines[] = 'namespace Sandadmin\Contract\Generated;';
        $lines[] = '';
        $lines[] = '/** 错误码注册表：码常量、HTTP 状态映射、全集清单。 */';
        $lines[] = 'final class ErrorCodeRegistry';
        $lines[] = '{';
        $lines[] = "    /** 成功码：成功信封固定返回 code=OK */";
        $lines[] = "    public const OK = 'OK';";
        $lines[] = '';
        $usedNames = [];
        foreach ($catalog as $code => $entry) {
            $name = $entry['const_name'] !== '' ? $entry['const_name'] : $code;
            if (isset($usedNames[$name])) {
                throw new \RuntimeException("错误码常量名冲突：{$name} 被 {$usedNames[$name]} 与 {$code} 同时声明");
            }
            $usedNames[$name] = $code;
            if ($name !== $code) {
                $lines[] = '    /** 线上错误码为 ' . $code . '，常量名按 4 词上限取短名 */';
            }
            $lines[] = '    public const ' . $name . ' = ' . var_export($code, true) . ';';
        }
        $lines[] = '';
        $lines[] = '    /** @var array<string, int> code => HTTP 状态 */';
        $statusMap = [];
        foreach ($catalog as $code => $entry) {
            $statusMap[$code] = $entry['http_status'];
        }
        $lines[] = '    public const HTTP_STATUS = ' . self::exportValue($statusMap, 1) . ';';
        $lines[] = '';
        $lines[] = '    /** @var list<string> 契约声明的全部错误码 */';
        $lines[] = '    public const ALL = ' . self::inlineList(array_keys($catalog)) . ';';
        $lines[] = '';
        $common = [];
        foreach ($catalog as $code => $entry) {
            if ($entry['common']) {
                $common[] = $code;
            }
        }
        $lines[] = '    /** @var list<string> 通用码：接口不必逐个声明 */';
        $lines[] = '    public const COMMON = ' . self::inlineList($common) . ';';
        $lines[] = '';
        $lines[] = '    public static function httpStatus(string $code): int';
        $lines[] = '    {';
        $lines[] = '        return self::HTTP_STATUS[$code] ?? 500;';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    public static function isDeclared(string $code): bool';
        $lines[] = '    {';
        $lines[] = '        return in_array($code, self::ALL, true);';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<McpSpec> $tools
     * @param list<string> $sourceLabels
     */
    public static function emitMcpRegistry(array $tools, array $sourceLabels, string $sourceHash): string
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', ''];
        foreach (SourceHash::headerLines($sourceLabels, $sourceHash) as $line) {
            $lines[] = '// ' . $line;
        }
        $lines[] = '';
        $lines[] = 'namespace Sandadmin\Contract\Generated;';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' * MCP 工具注册表：与 ToolSpec 同形状的机械规格清单。';
        $lines[] = ' * runner 不进注册表，留在代码侧装配。';
        $lines[] = ' * 用静态方法而非 const：类常量不能含 stdClass，';
        $lines[] = ' * 而 JSON Schema 的 properties 空集在 PHP 侧必须是对象。';
        $lines[] = ' */';
        $lines[] = 'final class McpToolRegistry';
        $lines[] = '{';
        $lines[] = '    /**';
        $lines[] = '     * @return list<array{name: string, title: string, description: string, tier: string, permission: string, route: string, dev_only: bool, input: array<string, mixed>}>';
        $lines[] = '     */';
        $lines[] = '    public static function specs(): array';
        $lines[] = '    {';
        $lines[] = '        return [';
        foreach ($tools as $tool) {
            $lines[] = '            [';
            $lines[] = '                \'name\' => ' . var_export($tool['name'], true) . ',';
            $lines[] = '                \'title\' => ' . var_export($tool['title'], true) . ',';
            $lines[] = '                \'description\' => ' . var_export($tool['description'], true) . ',';
            $lines[] = '                \'tier\' => ' . var_export($tool['tier'], true) . ',';
            $lines[] = '                \'permission\' => ' . var_export($tool['permission'], true) . ',';
            $lines[] = '                \'route\' => ' . var_export($tool['route'], true) . ',';
            $lines[] = '                \'dev_only\' => ' . ($tool['dev_only'] ? 'true' : 'false') . ',';
            $lines[] = '                \'input\' => ' . self::exportSchema($tool['input'], 4) . ',';
            $lines[] = '            ],';
        }
        $lines[] = '        ];';
        $lines[] = '    }';
        $lines[] = '';
        $lines[] = '    /** @return array<string, string> 工具名 => 权限码 */';
        $lines[] = '    public static function permissions(): array';
        $lines[] = '    {';
        $lines[] = '        $map = [];';
        $lines[] = '        foreach (self::specs() as $spec) {';
        $lines[] = '            $map[$spec[\'name\']] = $spec[\'permission\'];';
        $lines[] = '        }';
        $lines[] = '';
        $lines[] = '        return $map;';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /** @param list<string> $values */
    private static function inlineList(array $values): string
    {
        $items = [];
        foreach ($values as $value) {
            $items[] = var_export($value, true);
        }

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * 短语法导出；空数组在 properties 位上由 exportSchema 处理成 stdClass。
     * @return string
     */
    public static function exportValue(mixed $value, int $level = 0): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return var_export($value, true);
        }
        if (!is_array($value)) {
            return var_export($value, true);
        }
        $indent = str_repeat('    ', $level);
        $inner = str_repeat('    ', $level + 1);
        $sequential = array_is_list($value);
        $parts = [];
        foreach ($value as $key => $item) {
            $line = $sequential ? '' : self::exportKey($key) . ' => ';
            $parts[] = $inner . $line . self::exportValue($item, $level + 1) . ',';
        }

        return "[\n" . implode("\n", $parts) . "\n{$indent}]";
    }

    /**
     * JSON Schema 导出：properties 为空集时发成 new \stdClass()，
     * 保证 json_encode 后是 {} 而不是 []（与装配层既有线报文一致）。
     * @param array<mixed, mixed> $schema
     */
    private static function exportSchema(array $schema, int $level): string
    {
        $indent = str_repeat('    ', $level);
        $inner = str_repeat('    ', $level + 1);
        $parts = [];
        foreach ($schema as $key => $item) {
            $keyCode = self::exportKey($key) . ' => ';
            if ($key === 'properties' && is_array($item) && $item === []) {
                $parts[] = $inner . $keyCode . 'new \stdClass(),';
                continue;
            }
            if ($key === 'properties' && is_array($item)) {
                $sub = [];
                $deeper = str_repeat('    ', $level + 2);
                foreach ($item as $name => $prop) {
                    $propCode = is_array($prop) ? self::exportSchema($prop, $level + 2) : self::exportValue($prop, $level + 2);
                    $sub[] = $deeper . self::exportKey((string) $name) . ' => ' . $propCode . ',';
                }
                $parts[] = $inner . $keyCode . "[\n" . implode("\n", $sub) . "\n{$inner}],";
                continue;
            }
            if (is_array($item)) {
                // enum/required 这类 list 走值序列，嵌套 map 继续递归
                $valueCode = array_is_list($item)
                    ? self::exportValue($item, $level + 1)
                    : self::exportSchema($item, $level + 1);
            } else {
                $valueCode = self::exportValue($item, $level + 1);
            }
            $parts[] = $inner . $keyCode . $valueCode . ',';
        }

        return "[\n" . implode("\n", $parts) . "\n{$indent}]";
    }

    private static function exportKey(string|int $key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        return var_export($key, true);
    }

    private static function classBasename(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * 权限码 → 常量名：去掉 system: 前缀（全仓主域），
     * 其余段大写蛇形拼接；mcp:dev:routes → MCP_DEV_ROUTES。
     */
    public static function permissionConst(string $code): string
    {
        $body = str_starts_with($code, 'system:') ? substr($code, 7) : $code;
        $name = strtoupper(str_replace([':', '-'], '_', $body));

        return $name;
    }

    /** 菜单键 → 常量名：system:user → SIDEBAR_USER（避让 system:menu:* 权限码前缀） */
    public static function menuConst(string $key): string
    {
        $body = str_starts_with($key, 'system:') ? substr($key, 7) : $key;
        $name = 'SIDEBAR_' . strtoupper(str_replace([':', '-'], '_', $body));

        return $name;
    }
}
