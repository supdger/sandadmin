<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\OrgActor;

/**
 * 把 MCP 身份落成 v1 业务层用的操作人（OrgActor）。
 * 实际权限 = 账号权限 ∩ 凭据 scope，只能缩小不能放大（U-3/M-01）；
 * 返回 null 表示身份落不到有效账号，调用方按未认证处理。
 */
interface McpActorSource
{
    public function resolve(McpIdentity $identity, Connection $connection): ?OrgActor;
}
