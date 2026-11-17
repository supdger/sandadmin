<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * MCP 工具登记条目（U-3/M-03）：工具名 → v1 路由 + 权限码 + 三档分级。
 * 显式登记，不走反射/注解发现；构建期从 OpenAPI x-mcp 生成注册表
 * 落地前，登记表在 Application\Mcp\ToolCatalog 里手写维护。
 * dev 工具的 route 为空串：它们是开发构建专用工具，不对应 v1 接口。
 */
final readonly class ToolSpec
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema 对象形状
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $description,
        public ToolTier $tier,
        public string $permission,
        public string $route,
        public array $inputSchema,
        public ToolRunner $runner,
    ) {
        if ($this->name === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $this->name)) {
            throw new \InvalidArgumentException('工具名只能含字母数字下划线连字符且不超过 64 字符');
        }
        if ($this->permission === '') {
            throw new \InvalidArgumentException('工具必须绑定权限码');
        }
    }
}
