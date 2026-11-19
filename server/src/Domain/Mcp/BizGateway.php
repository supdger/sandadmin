<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Mcp;

/**
 * 业务工具的执行端口：把一个登记过的业务操作交给 v1 同一套业务层跑。
 * operation 是 BusinessCatalog 登记表里的操作键，不是路由或方法名；
 * 实现负责再查一次账号权限（与 v1 控制器同一判定），不凭调用方自述。
 */
interface BizGateway
{
    /** @param array<string, mixed> $arguments */
    public function call(McpIdentity $identity, string $operation, array $arguments, CallContext $context): BizReply;
}
