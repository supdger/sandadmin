<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Error;

/** HTTP 错误体。fields 为字段名到错误说明的映射，编码时用 JSON 对象。 */
final readonly class ErrorBody
{
    /** @param array<string, string> $fields */
    public function __construct(
        public int $status,
        public string $code,
        public string $message,
        public array $fields,
        public string $requestId,
    ) {
    }
}
