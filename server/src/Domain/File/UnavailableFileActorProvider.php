<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * 默认实现：一律返回 null（未登录）。保证 /api/** 在鉴权底座合入前
 * 不放行任何文件接口请求；测试注入替身覆盖。
 */
final class UnavailableFileActorProvider implements FileActorProvider
{
    public function actor(object $request): ?FileActor
    {
        return null;
    }
}
