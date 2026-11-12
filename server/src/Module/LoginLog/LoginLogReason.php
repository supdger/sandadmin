<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Module\LoginLog;

/**
 * reason 枚举 → 页面原因文案（规格 login-log.md 5.2）；
 * 登录事件 message → reason 枚举（落库时由监听器用）。
 * 成功 reason 为 null，文案同样返回 null（页面显示空白，不显示「-」）。
 */
final class LoginLogReason
{
    private const TEXT = [
        'bad_password' => '密码不对',
        'disabled' => '账号已停用',
        'locked' => '输错次数太多已锁定',
        'captcha_failed' => '没完成安全验证',
        'user_not_found' => '没有这个账号',
        'rate_limited' => '尝试太频繁，请稍后再试',
        'otp_invalid' => '一次性口令不对',
        'otp_expired' => '一次性口令已过期',
    ];

    public static function text(?string $reason): ?string
    {
        if ($reason === null || $reason === '') {
            return null;
        }

        return self::TEXT[$reason] ?? null;
    }

    /**
     * 登录链只发中文 message（如「账号或密码错误」「您已被禁止登录」），
     * 这里按关键字落到 ck_sand_system_login_log_reason 允许的枚举。
     * 插件对「账号不存在」不发事件，到这里的失败都先按 bad_password 归。
     */
    public static function fromMessage(string $message): string
    {
        return match (true) {
            str_contains($message, '禁止登录'), str_contains($message, '已停用') => 'disabled',
            str_contains($message, '锁定') => 'locked',
            str_contains($message, '验证码'), str_contains($message, '安全验证') => 'captcha_failed',
            str_contains($message, '频繁'), str_contains($message, '太多次') => 'rate_limited',
            str_contains($message, '口令') && str_contains($message, '过期') => 'otp_expired',
            str_contains($message, '口令') => 'otp_invalid',
            default => 'bad_password',
        };
    }
}
