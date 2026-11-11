<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Exception;

/** 请求头或请求元数据非法。映射为契约错误码 VALIDATION_FAILED。 */
final class InvalidRequestException extends DomainException
{
    /** @param array<string, string> $fields */
    public function __construct(string $message, array $fields, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'VALIDATION_FAILED', $fields, 400, $previous);
    }
}
