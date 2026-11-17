<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Log;

use RuntimeException;

/** 日志留存配置非法：通道未配置、kind 非法或 days 低于类别下限。 */
final class LogRetentionException extends RuntimeException
{
}
