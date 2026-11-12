<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\Lifecycle;

/**
 * B2 统一鉴权合入前的默认实现：一律视为未登录。
 * TODO(#76): 请求上下文在 #76 接入，届时按会话与权限清单解析。
 */
final class UnavailableLifecycleActorProvider implements LifecycleActorProvider
{
    public function actor(object $request): ?LifecycleActor
    {
        return null;
    }
}
