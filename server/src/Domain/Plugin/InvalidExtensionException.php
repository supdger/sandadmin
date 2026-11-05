<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Plugin;

/** 扩展声明或已落库载荷不合法。由上层 ErrorMiddleware 映射。 */
final class InvalidExtensionException extends \DomainException
{
    /** @param array<string, string> $fields */
    public function __construct(string $message, public readonly array $fields = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
