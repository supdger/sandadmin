<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Org;

/**
 * GET /users/{user_id}/disable-check 的权限。
 * 停用和删除都会先看这份清单，所以持有更新或删除任一权限即可。
 */
final class DisableCheckAccess
{
    public const UPDATE = 'system:user:update';

    public const DELETE = 'system:user:delete';

    /** @param list<string> $held */
    public static function allowed(array $held): bool
    {
        foreach ($held as $code) {
            if ($code === self::UPDATE || $code === self::DELETE) {
                return true;
            }
        }

        return false;
    }
}
