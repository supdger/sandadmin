<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Storage\Exception;

class StorageException extends \RuntimeException
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
