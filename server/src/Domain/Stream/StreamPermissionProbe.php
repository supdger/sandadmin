<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

/**
 * 流式权限复查端口（J-5：每 30 秒复查一次权限版本号）。鉴权侧实现
 * 按主体取当前 authz_version；返回值与开流时快照不一致即视为撤权。
 * 返回 null 表示取不到版本——按撤权处理（鉴权故障 fail-closed）。
 */
interface StreamPermissionProbe
{
    public function authzVersion(string $subject): ?int;
}
