<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use Sandadmin\Domain\Auth\RequestContext;
use Webman\Http\Request;

/**
 * 把 RequestContext 放在 Workerman Request::$context 上。
 * 该数组属于当前请求对象，Request::destroy() 会清空；这里不使用静态属性。
 */
final class RequestContextStore
{
    public const ATTRIBUTE = 'sandadmin.request_context';

    public function set(Request $request, RequestContext $context): void
    {
        $request->context[self::ATTRIBUTE] = $context;
    }

    public function get(Request $request): ?RequestContext
    {
        $value = $request->context[self::ATTRIBUTE] ?? null;

        return $value instanceof RequestContext ? $value : null;
    }
}
