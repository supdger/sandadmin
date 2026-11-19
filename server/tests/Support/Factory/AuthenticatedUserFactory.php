<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Support\Factory;

use Sandadmin\Domain\Auth\AuthenticatedUser;

/** Unit 用内存工厂：不连库造已认证身份值对象。 */
final class AuthenticatedUserFactory
{
    public static function make(
        string $id = '1',
        int $authzVersion = 1,
    ): AuthenticatedUser {
        return new AuthenticatedUser($id, $authzVersion);
    }
}
