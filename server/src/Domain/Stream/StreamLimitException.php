<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Stream;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Domain\Exception\DomainException;

/** 进程挂路数超限的拒绝（J-5：超限返回 429 或排队）。 */
final class StreamLimitException extends DomainException
{
    public function __construct(string $message = '流式连接数已达上限，稍后再试', ?\Throwable $previous = null)
    {
        parent::__construct($message, ErrorCodeRegistry::STREAM_LIMIT_REACHED, [], 429, $previous);
    }
}
