<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Mcp;

use Sandadmin\Domain\Mcp\ToolSpec;

/**
 * MCP 工具登记表：显式清单，不是反射/注解扫描出来的（U-3/M-03）。
 * 构建期从 OpenAPI x-mcp 生成注册表落地前，清单在开发构建工具登记表与
 * 后续的业务登记表里手写维护；启动期一致性校验归后续版本。
 */
final class ToolCatalog
{
    /** @var array<string, ToolSpec> */
    private readonly array $specs;

    /** @param list<ToolSpec> $specs */
    public function __construct(array $specs)
    {
        $map = [];
        foreach ($specs as $spec) {
            if (isset($map[$spec->name])) {
                throw new \InvalidArgumentException('MCP 工具名重复登记：' . $spec->name);
            }
            $map[$spec->name] = $spec;
        }
        $this->specs = $map;
    }

    /** @return list<ToolSpec> */
    public function all(): array
    {
        return array_values($this->specs);
    }

    public function find(string $name): ?ToolSpec
    {
        return $this->specs[$name] ?? null;
    }
}
