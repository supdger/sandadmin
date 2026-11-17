<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Dev;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Mcp\Dev\LogRedactor;

/** 日志/命令输出脱敏：密钥、令牌、手机号、邮箱不原样出镜。 */
final class LogRedactorTest extends TestCase
{
    public function testKeyValuesRedacted(): void
    {
        $line = 'login failed password=hunter2 token=abc123456789 secret: xyz';
        $redacted = LogRedactor::redact($line);

        self::assertStringNotContainsString('hunter2', $redacted);
        self::assertStringNotContainsString('abc123456789', $redacted);
        self::assertStringNotContainsString('xyz', $redacted);
    }

    public function testBearerAndLongHex(): void
    {
        $hex = str_repeat('a1b2', 16);
        $redacted = LogRedactor::redact('Authorization: Bearer ' . $hex . ' raw=' . $hex);

        self::assertStringNotContainsString($hex, $redacted);
    }

    public function testPhoneAndMail(): void
    {
        $redacted = LogRedactor::redact('user 13812345678 mail a.b+c@example.com');

        self::assertStringNotContainsString('13812345678', $redacted);
        self::assertStringNotContainsString('a.b+c@example.com', $redacted);
    }

    public function testNormalLineUntouched(): void
    {
        $line = '[2026-11-17 10:00:00] route not found /api/x';

        self::assertSame($line, LogRedactor::redact($line));
    }
}
