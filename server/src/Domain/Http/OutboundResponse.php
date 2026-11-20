<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Domain\Http;

/** 一次出站 HTTP 响应。headers 键统一小写，同名多值按顺序保留在列表里。 */
final readonly class OutboundResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public bool $truncated = false,
    ) {
    }

    /** 取响应头首个值；键不区分大小写，未命中返回 null。 */
    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;

        return is_array($values) && $values !== [] ? $values[0] : null;
    }
}
