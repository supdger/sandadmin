<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Auth;

use Sandadmin\Domain\Exception\SessionExpiredException;
use Sandadmin\Domain\Exception\UnauthenticatedException;

/**
 * 校验会话令牌并返回用户。实现按 sand_system_session 口径：
 * 库里只存令牌 SHA-256 哈希，校验 revoked_time 与 expire_time。
 * 实现不得把用户写入静态属性、单例或 $GLOBALS。
 */
interface Authenticator
{
    /**
     * @throws UnauthenticatedException 令牌缺失、伪造或用户不可用
     * @throws SessionExpiredException 会话过期或已被吊销（含账号停用连带失效）
     */
    public function authenticate(string $token): AuthenticatedUser;
}
