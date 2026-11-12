<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Auth;

/**
 * sand_system_session 对应的浏览器会话 Cookie 契约常量（api-conventions §4）。
 * 写入方必须带 HttpOnly + Secure + SameSite=Lax + Path=/；浏览器端不接触令牌。
 */
final class SessionCookie
{
    public const NAME = 'sandadmin_session';

    public const PATH = '/';

    public const SAME_SITE = 'Lax';
}
