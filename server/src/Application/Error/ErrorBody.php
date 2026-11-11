<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Application\Error;

/**
 * HTTP 错误体，对应契约 ErrorEnvelope：{code,message,fields,request_id}。
 * fields 为字段名到错误文案列表的映射（{字段名: [文案,...]}），编码时用 JSON 对象。
 */
final readonly class ErrorBody
{
    /** @param array<string, list<string>> $fields */
    public function __construct(
        public int $status,
        public string $code,
        public string $message,
        public array $fields,
        public string $requestId,
    ) {
    }
}
