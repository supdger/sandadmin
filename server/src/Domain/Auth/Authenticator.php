<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Auth;

use Sandadmin\Domain\Exception\UnauthenticatedException;

/**
 * 校验调用方传入的凭证并返回用户。
 * 实现不得把用户写入静态属性、单例或 $GLOBALS。
 */
interface Authenticator
{
    /** @throws UnauthenticatedException 凭证缺失、伪造或已失效 */
    public function authenticate(string $credential): AuthenticatedUser;
}
