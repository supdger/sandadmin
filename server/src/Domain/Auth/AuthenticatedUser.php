<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Auth;

/** 本请求已认证的用户。不含凭证。 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public string $id,
        public int $authzVersion,
    ) {
        if ($this->id === '') {
            throw new \InvalidArgumentException('用户标识不能为空');
        }
        // sand_system_user.authz_version 默认 1 并随权限变更自增，从 1 起
        if ($this->authzVersion < 1) {
            throw new \InvalidArgumentException('authz version 必须 ≥ 1');
        }
    }
}
