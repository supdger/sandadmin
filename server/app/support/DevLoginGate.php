<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace app\support;

use support\Request;

/**
 * 开发本机登录放行判定：环境开关 + 真实回环对端 + 无转发头。
 * 出现 X-Forwarded-For / X-Real-IP / Forwarded 说明前面有代理，
 * getRemoteIp() 看到的「回环」不可信，一律拒绝；
 * 纯函数部分可脱离连接单测。
 */
final class DevLoginGate
{
    public const DEV_USERNAME = 'admin';

    private const LOOPBACK_IPS = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];

    private const FORWARDED_HEADERS = ['x-forwarded-for', 'x-real-ip', 'forwarded'];

    public static function enabled(): bool
    {
        return env('SAND_DEV_LOGIN') === '1';
    }

    public static function allowed(Request $request): bool
    {
        return self::allows(self::enabled(), $request->getRemoteIp(), self::hasForwardedHeaders($request));
    }

    public static function allows(bool $enabled, string $remoteIp, bool $hasForwardedHeaders): bool
    {
        return $enabled
            && in_array($remoteIp, self::LOOPBACK_IPS, true)
            && !$hasForwardedHeaders;
    }

    public static function hasForwardedHeaders(Request $request): bool
    {
        foreach (self::FORWARDED_HEADERS as $header) {
            $value = $request->header($header);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
