<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Webman\Http\Request;

/** 读取单个请求头。只接受字符串，空串视为缺失。 */
final class RequestHeaders
{
    public function optional(Request $request, string $name): ?string
    {
        $value = $request->header($name);
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
