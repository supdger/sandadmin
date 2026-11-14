<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\LoginLog;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\LoginLog\LoginLogReason;

/** M2 失败原因：枚举到中文提示逐字对照，未知值回落到安全文案。 */
final class LoginLogReasonTest extends TestCase
{
    public function testKnownReasonsMapToHumanText(): void
    {
        self::assertSame('密码不对', LoginLogReason::text('bad_password'));
        self::assertSame('账号已停用', LoginLogReason::text('disabled'));
        self::assertSame('输错次数太多已锁定', LoginLogReason::text('locked'));
        self::assertSame('没完成安全验证', LoginLogReason::text('captcha_failed'));
        self::assertSame('没有这个账号', LoginLogReason::text('user_not_found'));
        self::assertSame('尝试太频繁，请稍后再试', LoginLogReason::text('rate_limited'));
        self::assertSame('一次性口令不对', LoginLogReason::text('otp_invalid'));
        self::assertSame('一次性口令已过期', LoginLogReason::text('otp_expired'));
    }

    public function testSuccessAndUnknownReasonGiveNoText(): void
    {
        // 成功的记录 reason 为空，页面上 reason_text 也要是空而不是「-」
        self::assertNull(LoginLogReason::text(null));
        self::assertNull(LoginLogReason::text(''));
        // 库里可能出现没登记过的失败值，不能报错也不能乱猜
        self::assertNull(LoginLogReason::text('something_else'));
    }

    public function testMessageKeywordsMapToReasonEnum(): void
    {
        self::assertSame('disabled', LoginLogReason::fromMessage('您已被禁止登录'));
        self::assertSame('locked', LoginLogReason::fromMessage('账号已锁定，请稍后再试'));
        self::assertSame('captcha_failed', LoginLogReason::fromMessage('请先完成验证码'));
        self::assertSame('rate_limited', LoginLogReason::fromMessage('尝试太频繁'));
        self::assertSame('otp_expired', LoginLogReason::fromMessage('一次性口令已过期'));
        self::assertSame('otp_invalid', LoginLogReason::fromMessage('一次性口令不对'));
        self::assertSame('bad_password', LoginLogReason::fromMessage('账号或密码错误'));
    }
}
