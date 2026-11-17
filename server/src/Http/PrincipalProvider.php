<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Module\Org\OrgActor;

/**
 * 请求操作人解析。开发构建由本机登录身份实现；生产包由
 * UnavailablePrincipalProvider 兜底，统一会话底座合入后接真实身份。
 */
interface PrincipalProvider
{
    public function resolve(object $request): ?OrgActor;
}
