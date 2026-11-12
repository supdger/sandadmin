<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

use Doctrine\DBAL\Connection;
use Sandadmin\Http\DevPrincipal;

/** 开发本机登录通过后，超级管理员可以查看全部生命周期申请。 */
final class DevLifecycleActorProvider implements LifecycleActorProvider
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function actor(object $request): ?LifecycleActor
    {
        $org = (new DevPrincipal($this->connection))->resolve($request);
        if ($org === null) {
            return null;
        }
        $viewAll = $org->isSuper || $org->can('system:plugin:index') || $org->can('system:update:index');

        return new LifecycleActor($org->userId, $viewAll);
    }
}
