<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Exception;

use Sandadmin\Contract\Generated\ErrorCodeRegistry;

final class SessionExpiredException extends DomainException
{
    public function __construct(string $message = '登录已过期，请重新登录', ?\Throwable $previous = null)
    {
        parent::__construct($message, ErrorCodeRegistry::SESSION_EXPIRED, [], 401, $previous);
    }
}
