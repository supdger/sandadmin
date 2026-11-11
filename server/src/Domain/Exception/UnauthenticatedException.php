<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Exception;

final class UnauthenticatedException extends DomainException
{
    public function __construct(string $message = '请先登录', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'UNAUTHENTICATED', [], 401, $previous);
    }
}
