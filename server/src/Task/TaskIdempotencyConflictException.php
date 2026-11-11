<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 青岛沙盒创意网络科技有限公司
 * SPDX-License-Identifier: MIT
 */

namespace Sandadmin\Task;

/**
 * 同一 (type, idempotency_key) 已存在任务记录；调用方应改取既有记录。
 */
final class TaskIdempotencyConflictException extends \RuntimeException
{
}
