<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Dev;

use Sandadmin\Domain\Mcp\ToolSpec;
use Sandadmin\Domain\Mcp\ToolTier;

/**
 * 开发构建专用 MCP 工具登记表：四条显式条目，只在 .sand-dev-build 标记
 * 存在时由入口装配（见 Http\McpController），生产制品里整表不出现。
 * 权限码用 mcp:dev:* 命名空间，与 v1 权限码区分；执行类工具按 write 档
 * 管（要幂等键、可 dry_run），只读类按 read 档。
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
        return [
            new ToolSpec(
                'dev_inspect_routes',
                '路由与契约检查',
                '读取当前路由表与契约文件（只读）',
                ToolTier::Read,
                'mcp:dev:routes',
                '',
                [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false,
                ],
                new RoutesTool($routeReader, $contractPaths),
            ),
            new ToolSpec(
                'dev_recent_errors',
                '最近错误与慢请求',
                '摘读本地日志中的错误与慢请求记录（只读、脱敏）',
                ToolTier::Read,
                'mcp:dev:errors',
                '',
                [
                    'type' => 'object',
                    'properties' => [
                        'kind' => ['type' => 'string', 'enum' => ['error', 'slow', 'all']],
                        'lines' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                    ],
                    'additionalProperties' => false,
                ],
                new ErrorsTool($logDir),
            ),
            new ToolSpec(
                'dev_run_phpunit',
                '运行选定 PHPUnit 测试',
                '按白名单目标运行 PHPUnit（tests/ 下路径或 Sandadmin\\Tests\\* 类名）',
                ToolTier::Write,
                'mcp:dev:phpunit',
                '',
                [
                    'type' => 'object',
                    'properties' => [
                        'target' => ['type' => 'string'],
                        'idempotency_key' => ['type' => 'string'],
                        'dry_run' => ['type' => 'boolean'],
                    ],
                    'required' => ['target', 'idempotency_key'],
                    'additionalProperties' => false,
                ],
                new PhpunitTool($serverRoot, $testTimeout),
            ),
            new ToolSpec(
                'dev_run_pre_merge',
                '运行快速门禁',
                '执行 tools/pre-merge.sh --fast（固定命令，无参数）',
                ToolTier::Write,
                'mcp:dev:gate',
                '',
                [
                    'type' => 'object',
                    'properties' => [
                        'idempotency_key' => ['type' => 'string'],
                        'dry_run' => ['type' => 'boolean'],
                    ],
                    'required' => ['idempotency_key'],
                    'additionalProperties' => false,
                ],
                new PreMergeTool($repoRoot, $gateTimeout),
            ),
        ];
    }
}
