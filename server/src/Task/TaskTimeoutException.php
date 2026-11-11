<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 单次执行超过配置的超时上限（含心跳续租仍超时的场景）。
 */
final class TaskTimeoutException extends \RuntimeException
{
}
