<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Exception;

/** 请求头或请求元数据非法（例如时区）。 */
final class InvalidRequestException extends DomainException
{
    /** @param array<string, string> $fields */
    public function __construct(string $message, array $fields, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'invalid_request', $fields, 400, $previous);
    }
}
