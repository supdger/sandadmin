<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 一次 MCP 调用解析出的身份。permissions 是「该凭据能调用的 v1 权限码」，
 * '*' 表示开发账号全量；实际权限 = 账号权限 ∩ 凭据 scope（U-3/M-01），
 * 本类只装解析结果，不再回查库。
 */
final readonly class McpIdentity
{
    public const CHANNEL = 'mcp';

    /**
     * @param list<string> $scopes      凭据 scope（如 mcp:dev、read、write）
     * @param list<string> $permissions 允许调用的权限码，['*'] 为全量
     */
    public function __construct(
        public string $subject,
        public string $credential,
        public array $scopes,
        public array $permissions,
    ) {
        if ($this->subject === '' || $this->credential === '') {
            throw new \InvalidArgumentException('身份主体与凭据标识不能为空');
        }
    }

    public function can(string $permission): bool
    {
        return in_array('*', $this->permissions, true) || in_array($permission, $this->permissions, true);
    }
}
