<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\File;

/**
 * 由请求解析文件操作身份的接缝。会话与权限底座（B2，#56）合入后，
 * 由基于 RequestContext 与权限清单的实现替换默认拒绝实现。
 */
interface FileActorProvider
{
    /**
     * @param object $request webman 请求对象；实现方自行收窄类型
     */
    public function actor(object $request): ?FileActor;
}
