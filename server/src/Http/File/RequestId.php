<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http\File;

final class RequestId
{
    public static function fromHeader(?string $header): string
    {
        if ($header !== null && preg_match('/\A[A-Za-z0-9_-]{8,64}\z/', $header) === 1) {
            return $header;
        }

        return bin2hex(random_bytes(8));
    }
}
