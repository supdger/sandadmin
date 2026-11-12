<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Announcement;

/** 从请求解析公告操作人；返回 null 表示未登录。 */
interface AnnouncementActorProvider
{
    public function actor(object $request): ?AnnouncementActor;
}
