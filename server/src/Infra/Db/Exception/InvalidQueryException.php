<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Db\Exception;

/** 列表查询参数非法（未知排序/筛选字段、越界分页等）。由上层 ErrorMiddleware 映射为 422。 */
final class InvalidQueryException extends \InvalidArgumentException
{
    /** @param array<string, string> $fields */
    public function __construct(string $message, public readonly array $fields = [])
    {
        parent::__construct($message);
    }
}