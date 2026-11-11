<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

/** 采用调用方传入的 request id；格式不安全时重新生成，避免把该值写进日志时注入换行。 */
final class RequestIds
{
    public const PATTERN = '/^[A-Za-z0-9_-]{8,128}$/';

    public function resolve(?string $incoming): string
    {
        if ($incoming !== null && preg_match(self::PATTERN, $incoming) === 1) {
            return $incoming;
        }

        return bin2hex(random_bytes(16));
    }
}
