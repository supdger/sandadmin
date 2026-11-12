<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

use Doctrine\DBAL\Connection;
use Sandadmin\Http\DevPrincipal;

/** 开发本机登录放行时，把操作人交给公告模块；其余情况仍是未登录。 */
final class DevAnnouncementActorProvider implements AnnouncementActorProvider
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function actor(object $request): ?AnnouncementActor
    {
        $principal = (new DevPrincipal($this->connection))->resolve($request);
        if ($principal === null) {
            return null;
        }

        return new AnnouncementActor($principal->userId, $principal->name, $principal->deptId, [], true);
    }
}
