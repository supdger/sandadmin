<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\LoginLog;

/** user_agent 合成「Mac 上的 Chrome」一类人话设备名；原文不上页面。 */
final class DeviceLabel
{
    public static function of(?string $userAgent): string
    {
        $ua = is_string($userAgent) ? $userAgent : '';
        $os = self::os($ua);
        $browser = self::browser($ua);
        if ($os === null && $browser === null) {
            return '未知设备';
        }
        if ($os === null) {
            return (string) $browser;
        }
        if ($browser === null) {
            return $os;
        }

        return $os . ' 上的 ' . $browser;
    }

    private static function os(string $ua): ?string
    {
        return match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => '安卓手机',
            str_contains($ua, 'Windows') => 'Windows 电脑',
            str_contains($ua, 'Mac OS X'), str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux 电脑',
            default => null,
        };
    }

    private static function browser(string $ua): ?string
    {
        return match (true) {
            str_contains($ua, 'Edg/'), str_contains($ua, 'EdgA/'), str_contains($ua, 'EdgiOS/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/'), str_contains($ua, 'FxiOS/') => 'Firefox',
            str_contains($ua, 'Chrome/'), str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            str_contains($ua, 'Trident'), str_contains($ua, 'MSIE') => 'IE',
            default => null,
        };
    }
}
