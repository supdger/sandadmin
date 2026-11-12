<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

/** 从请求解析生命周期申请人；返回 null 表示未登录。 */
interface LifecycleActorProvider
{
    public function actor(object $request): ?LifecycleActor;
}
