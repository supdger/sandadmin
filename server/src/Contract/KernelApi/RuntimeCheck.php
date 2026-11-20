<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Contract\KernelApi;

use Sandadmin\Contract\Build\ConflictCheck;
use Sandadmin\Contract\Build\ContractDoc;

/**
 * kernel_api 运行时对齐检查：有契约片段的插件包，
 * 代码面与产物面必须和片段一一对应——生成的路由薄壳/路由表、
 * Permission 注解码、x-handler 落盘文件。
 * 只被 FragmentCheck 与 ContractBuild::checkKernelApi 共用，纯文本比对不加载插件代码。
 *
 * @phpstan-import-type ParsedDoc from ContractDoc
 */
final class RuntimeCheck
{
    /**
     * route.generated.php 里 Route::verb('path') 的事实表正则，
     * 与生成表发射形态（emitRoutes）逐字对应。
     */
    private const ROUTE_PATTERN = "/Route::(get|post|put|patch|delete|head|options)\\('([^']+)'/";

    /**
     * 薄壳里允许的 Route:: 调用：disableDefaultRoute 是薄壳职责，
     * fallback 与宿主 route.php 哨兵口径一致放行；其余调用都算手写注册。
     */
    private const SHELL_ALLOWED_CALLS = ['disableDefaultRoute', 'fallback'];

    /**
     * Permission 注解实参段：兼容单参（码）、双参（名+码）与
     * 命名参数形态，允许带命名空间前缀（plugin\xxx\Permission）。
     */
    private const PERMISSION_CALL_PATTERN = '/#\[\s*\\\\?(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*Permission\s*\((.*?)\)\s*\]/s';

    /**
     * @param ParsedDoc $doc 该插件片段的解析结果
     * @return list<string> 问题清单；空数组表示对齐
     */
    public static function check(string $pluginDir, array $doc): array
    {
        $app = basename(rtrim($pluginDir, '/'));

        return array_merge(
            self::checkRoutes($pluginDir, $app, $doc),
            self::checkPermissions($pluginDir, $app, $doc),
            self::checkHandlers($pluginDir, $app, $doc),
        );
    }

    /**
     * 薄壳形态 + 路由表集合与契约 paths×methods 双向恰好相等。
     * @param ParsedDoc $doc
     * @return list<string>
     */
    private static function checkRoutes(string $pluginDir, string $app, array $doc): array
    {
        $problems = [];
        $shellFile = $pluginDir . '/config/route.php';
        if (!is_file($shellFile)) {
            $problems[] = "{$app}: config/route.php 缺失，契约插件必须是生成的薄壳";
        } else {
            $shell = file_get_contents($shellFile);
            if ($shell === false) {
                $problems[] = "{$app}: config/route.php 不可读";
            } else {
                if (!str_contains($shell, 'route.generated.php')) {
                    $problems[] = "{$app}: config/route.php 缺少对 route.generated.php 的 require，不是生成的薄壳";
                }
                if (preg_match('/Route::disableDefaultRoute\s*\(/', $shell) !== 1) {
                    $problems[] = "{$app}: config/route.php 缺少 Route::disableDefaultRoute，/app/ 默认路由会复活";
                }
                if (preg_match_all('/Route::([a-zA-Z]+)\s*\(/', $shell, $matches) !== false) {
                    foreach ($matches[1] as $call) {
                        if (!in_array($call, self::SHELL_ALLOWED_CALLS, true)) {
                            $problems[] = "{$app}: config/route.php 含手写 Route::{$call} 注册，契约插件的路由只能来自 route.generated.php";
                        }
                    }
                }
            }
        }

        $tableFile = $pluginDir . '/config/route.generated.php';
        if (!is_file($tableFile)) {
            $problems[] = "{$app}: config/route.generated.php 缺失，请用契约构建重新生成";

            return $problems;
        }
        $table = file_get_contents($tableFile);
        if ($table === false) {
            $problems[] = "{$app}: config/route.generated.php 不可读";

            return $problems;
        }
        $emitted = self::routeSetOf($table);
        $declared = [];
        foreach ($doc['operations'] as $op) {
            $declared[$op['method'] . ' ' . $op['path']] = true;
        }
        foreach (array_keys($declared) as $route) {
            if (!isset($emitted[$route])) {
                $problems[] = "{$app}: 路由 {$route} 缺失：契约已声明但 route.generated.php 未注册";
            }
        }
        foreach (array_keys($emitted) as $route) {
            if (!isset($declared[$route])) {
                $problems[] = "{$app}: 路由 {$route} 多出：route.generated.php 注册了契约外的路由";
            }
        }

        return $problems;
    }

    /**
     * app/ 下全部 PHP 文件里 Permission 注解的码集合与片段全部
     * x-permission 具体码（含 x-permissions、权限树动作、MCP 工具码）
     * 双向恰好相等；保留字面量（public/authenticated/super）不在码形状内，自然跳过。
     * @param ParsedDoc $doc
     * @return list<string>
     */
    private static function checkPermissions(string $pluginDir, string $app, array $doc): array
    {
        $problems = [];
        $declared = ConflictCheck::collectPermissions(['' => $doc]);
        $annotated = self::scanPermissionCodes($pluginDir . '/app');
        foreach (array_keys($declared) as $code) {
            if (!isset($annotated[$code])) {
                $problems[] = "{$app}: 权限码 {$code} 缺失：契约已声明但代码里没有对应 Permission 注解";
            }
        }
        foreach (array_keys($annotated) as $code) {
            if (!isset($declared[$code])) {
                $problems[] = "{$app}: 权限码 {$code} 多出：Permission 注解声明了契约外的码";
            }
        }

        return $problems;
    }

    /**
     * x-handler 必须落在 plugin\<插件命名空间>\ 下并映射到包内真实文件；
     * 插件命名空间 = 目录名去连字符（webman 的 /app/<插件>/ 归一同理）。
     * @param ParsedDoc $doc
     * @return list<string>
     */
    private static function checkHandlers(string $pluginDir, string $app, array $doc): array
    {
        $problems = [];
        $expectedNs = 'plugin\\' . str_replace('-', '', $app) . '\\';
        foreach ($doc['operations'] as $op) {
            $handler = $op['handler'];
            $label = $op['method'] . ' ' . $op['path'];
            if ($handler === '' || !str_contains($handler, '::')) {
                // 形状问题由 ContractDoc 已报，不重复
                continue;
            }
            $parts = explode('::', $handler, 2);
            $class = $parts[0];
            $method = $parts[1] ?? '';
            if ($method === '') {
                continue;
            }
            if (!str_starts_with($class, $expectedNs)) {
                $problems[] = "{$app}: {$label} 的 x-handler「{$handler}」不在 {$expectedNs} 命名空间下";
                continue;
            }
            $relative = str_replace('\\', '/', substr($class, strlen($expectedNs)));
            $file = $pluginDir . '/' . $relative . '.php';
            if (!is_file($file)) {
                $problems[] = "{$app}: {$label} 的 x-handler「{$handler}」对应文件缺失：{$relative}.php";
                continue;
            }
            $content = file_get_contents($file);
            if ($content === false || !str_contains($content, 'function ' . $method . '(')) {
                $problems[] = "{$app}: {$label} 的 x-handler「{$handler}」对应文件里没有 function {$method}(";
            }
        }

        return $problems;
    }

    /**
     * 解析 Route::verb('path') 事实表。
     * @return array<string, true> "METHOD /path" => true
     */
    private static function routeSetOf(string $content): array
    {
        $routes = [];
        if (preg_match_all(self::ROUTE_PATTERN, $content, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $routes[strtoupper($match[1]) . ' ' . $match[2]] = true;
            }
        }

        return $routes;
    }

    /**
     * 扫目录下全部 *.php，取 Permission 注解实参里权限码形状的字面量。
     * @return array<string, true>
     */
    private static function scanPermissionCodes(string $appDir): array
    {
        $codes = [];
        if (!is_dir($appDir)) {
            return $codes;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }
            if (preg_match_all(self::PERMISSION_CALL_PATTERN, $content, $calls) === false) {
                continue;
            }
            foreach ($calls[1] as $args) {
                if (preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"/', $args, $literals) === false) {
                    continue;
                }
                foreach (array_merge($literals[1], $literals[2]) as $literal) {
                    if (ContractDoc::isPermissionCode($literal)) {
                        $codes[$literal] = true;
                    }
                }
            }
        }

        return $codes;
    }
}
