<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源哈希: sha256:cf82ee2f6dbd21796934cc4f544ba247c1bbfc8594af44eb80669fad02a37fc2
// 生成器: contract-build v1

namespace Sandadmin\Contract\Generated;

/**
 * MCP 工具注册表：与 ToolSpec 同形状的机械规格清单。
 * runner 不进注册表，留在代码侧装配。
 * 用静态方法而非 const：类常量不能含 stdClass，
 * 而 JSON Schema 的 properties 空集在 PHP 侧必须是对象。
 */
final class McpToolRegistry
{
    /**
     * @return list<array{name: string, title: string, description: string, tier: string, permission: string, route: string, dev_only: bool, input: array<string, mixed>}>
     */
    public static function specs(): array
    {
        return [
            [
                'name' => 'dev_inspect_routes',
                'title' => '路由与契约检查',
                'description' => '读取当前路由表与契约文件（只读）',
                'tier' => 'read',
                'permission' => 'mcp:dev:routes',
                'route' => '',
                'dev_only' => true,
                'input' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'dev_recent_errors',
                'title' => '最近错误与慢请求',
                'description' => '摘读本地日志中的错误与慢请求记录（只读、脱敏）',
                'tier' => 'read',
                'permission' => 'mcp:dev:errors',
                'route' => '',
                'dev_only' => true,
                'input' => [
                    'type' => 'object',
                    'properties' => [
                        'kind' => [
                            'type' => 'string',
                            'enum' => [
                                'error',
                                'slow',
                                'all',
                            ],
                        ],
                        'lines' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 200,
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'dev_run_phpunit',
                'title' => '运行选定 PHPUnit 测试',
                'description' => '按白名单目标运行 PHPUnit（tests/ 下路径或 Sandadmin\\Tests\\* 类名）',
                'tier' => 'write',
                'permission' => 'mcp:dev:phpunit',
                'route' => '',
                'dev_only' => true,
                'input' => [
                    'type' => 'object',
                    'properties' => [
                        'target' => [
                            'type' => 'string',
                        ],
                        'idempotency_key' => [
                            'type' => 'string',
                        ],
                        'dry_run' => [
                            'type' => 'boolean',
                        ],
                    ],
                    'required' => [
                        'target',
                        'idempotency_key',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'dev_run_pre_merge',
                'title' => '运行快速门禁',
                'description' => '执行 tools/pre-merge.sh --fast（固定命令，无参数）',
                'tier' => 'write',
                'permission' => 'mcp:dev:gate',
                'route' => '',
                'dev_only' => true,
                'input' => [
                    'type' => 'object',
                    'properties' => [
                        'idempotency_key' => [
                            'type' => 'string',
                        ],
                        'dry_run' => [
                            'type' => 'boolean',
                        ],
                    ],
                    'additionalProperties' => false,
                    'required' => [
                        'idempotency_key',
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, string> 工具名 => 权限码 */
    public static function permissions(): array
    {
        $map = [];
        foreach (self::specs() as $spec) {
            $map[$spec['name']] = $spec['permission'];
        }

        return $map;
    }
}
