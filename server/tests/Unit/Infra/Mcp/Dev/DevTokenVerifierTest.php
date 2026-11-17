<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Dev;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Mcp\DevTokenVerifier;

/**
 * 开发令牌校验：标记缺失（生产制品）一律拒绝，令牌比对用 hash_equals。
 * 测试用令牌只存在于进程内环境变量，不落任何文件。
 */
final class DevTokenVerifierTest extends TestCase
{
    private const TEST_TOKEN = 'test-only-token-0123456789abcdef';

    protected function tearDown(): void
    {
        putenv(DevTokenVerifier::ENV_NAME);
        unset($_ENV[DevTokenVerifier::ENV_NAME]);
        parent::tearDown();
    }

    public function testRejectedWithoutDevBuild(): void
    {
        putenv(DevTokenVerifier::ENV_NAME . '=' . self::TEST_TOKEN);
        $verifier = new DevTokenVerifier(false);

        self::assertFalse(DevTokenVerifier::configured(false));
        self::assertNull($verifier->verify(self::TEST_TOKEN));
    }

    public function testRejectedWithoutTokenEnv(): void
    {
        $verifier = new DevTokenVerifier(true);

        self::assertFalse(DevTokenVerifier::configured(true));
        self::assertNull($verifier->verify(self::TEST_TOKEN));
    }

    public function testAcceptedWithBoth(): void
    {
        putenv(DevTokenVerifier::ENV_NAME . '=' . self::TEST_TOKEN);
        $verifier = new DevTokenVerifier(true);

        self::assertTrue(DevTokenVerifier::configured(true));
        $identity = $verifier->verify(self::TEST_TOKEN);
        self::assertNotNull($identity);
        self::assertSame('dev-token', $identity->credential);
        self::assertTrue($identity->can('system:anything:goes'));
        self::assertNull($verifier->verify('wrong-token-0123456789'));
        self::assertNull($verifier->verify(''));
    }

    public function testShortTokenRejected(): void
    {
        putenv(DevTokenVerifier::ENV_NAME . '=short');
        $verifier = new DevTokenVerifier(true);

        self::assertFalse(DevTokenVerifier::configured(true));
        self::assertNull($verifier->verify('short'));
    }
}
