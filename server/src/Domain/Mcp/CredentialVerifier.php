<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * MCP 凭据校验端口（U-3/M-01）。
 * 本期实现只有开发构建的本机开发令牌；正式机器令牌
 * （sand_system_api_token，摘要 + scope + 过期 + authz_version 联动）
 * 上线时另写实现，调用方不变。
 */
interface CredentialVerifier
{
    /** 校验失败返回 null，不抛异常（失败原因记审计/日志，不给调用方细节）。 */
    public function verify(string $token): ?McpIdentity;
}
