<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Idempotency;

/** 幂等键并发冲突：同 (user_id, key) 已被另一请求占用，事务已回滚。 */
final class IdempotencyConflict extends \RuntimeException
{
}
