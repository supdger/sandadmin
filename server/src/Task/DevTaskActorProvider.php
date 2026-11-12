<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Task;

use Doctrine\DBAL\Connection;
use Sandadmin\Http\DevPrincipal;

/** 开发本机登录通过后，超级管理员可以下载自己发起的导出文件。 */
final class DevTaskActorProvider implements TaskActorProvider
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function actor(object $request): ?TaskActor
    {
        $org = (new DevPrincipal($this->connection))->resolve($request);
        if ($org === null) {
            return null;
        }
        $viewAll = $org->isSuper || $org->can('system:task:index');

        return new TaskActor($org->userId, $viewAll);
    }
}
