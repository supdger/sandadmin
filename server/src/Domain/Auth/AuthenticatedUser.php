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
        public int $permissionVersion,
        public string $timezone = 'UTC',
    ) {
        if ($this->id === '') {
            throw new \InvalidArgumentException('用户标识不能为空');
        }
        if ($this->permissionVersion < 0) {
            throw new \InvalidArgumentException('permission version 必须 ≥ 0');
        }
        try {
            new \DateTimeZone($this->timezone);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('用户时区无效', 0, $e);
        }
    }
}
