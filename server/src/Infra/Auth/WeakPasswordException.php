<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Auth;

/** 弱密码词表文件不可用（缺失、不可读、为空）。由启动自检决定放行或拒启。 */
final class WeakPasswordException extends \RuntimeException
{
}
