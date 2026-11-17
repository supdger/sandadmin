<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

use Sandadmin\Contract\Generated\McpToolRegistry;
use Sandadmin\Domain\Mcp\ToolRunner;
use Sandadmin\Domain\Mcp\ToolSpec;
use Sandadmin\Domain\Mcp\ToolTier;

/**
 * 开发构建专用 MCP 工具装配：工具的机械规格（名、档、权限码、input schema）
 * 从契约生成的 McpToolRegistry 读取，本类只负责把 runner 按名对上。
 * 整条表只在 .sand-dev-build 标记存在时由入口装配（见 Http\McpController），
 * 生产制品里不出现。权限码用 mcp:dev:* 命名空间，与 v1 权限码区分。
 */
final class DevCatalog
{
    /**
     * @param callable(): list<array{methods: list<string>, path: string, callback: string}> $routeReader
     * @param list<string>                                                                   $contractPaths
     *
     * @return list<ToolSpec>
     */
    public static function specs(
        string $serverRoot,
        string $repoRoot,
        string $logDir,
        array $contractPaths,
        callable $routeReader,
        int $testTimeout = PhpunitTool::DEFAULT_TIMEOUT,
        int $gateTimeout = PreMergeTool::DEFAULT_TIMEOUT,
    ): array {
        $runners = self::runners($serverRoot, $repoRoot, $logDir, $contractPaths, $routeReader, $testTimeout, $gateTimeout);

        $specs = [];
        foreach (McpToolRegistry::specs() as $spec) {
            if ($spec['dev_only'] !== true) {
                // 非 dev 工具的装配面在正式链（B2 统一身份层），本表只管 dev 工具
                continue;
            }
            $runner = $runners[$spec['name']] ?? null;
            if ($runner === null) {
                // 契约声明了 dev 工具但代码侧没有 runner：静默跳过会让工具
                // 从 tools/list 消失却查无原因，按 fail-closed 直接报错
                throw new \RuntimeException('MCP 工具 ' . $spec['name'] . ' 缺少 runner 装配');
            }
            $specs[] = new ToolSpec(
                $spec['name'],
                $spec['title'],
                $spec['description'],
                ToolTier::from($spec['tier']),
                $spec['permission'],
                $spec['route'],
                $spec['input'],
                $runner,
            );
        }

        return $specs;
    }

    /**
     * runner 映射留在代码侧：进注册表的只有机械规格。
     *
     * @param callable(): list<array{methods: list<string>, path: string, callback: string}> $routeReader
     * @param list<string>                                                                   $contractPaths
     *
     * @return array<string, ToolRunner>
     */
    private static function runners(
        string $serverRoot,
        string $repoRoot,
        string $logDir,
        array $contractPaths,
        callable $routeReader,
        int $testTimeout,
        int $gateTimeout,
    ): array {
        return [
            'dev_inspect_routes' => new RoutesTool($routeReader, $contractPaths),
            'dev_recent_errors' => new ErrorsTool($logDir),
            'dev_run_phpunit' => new PhpunitTool($serverRoot, $testTimeout),
            'dev_run_pre_merge' => new PreMergeTool($repoRoot, $gateTimeout),
        ];
    }
}
