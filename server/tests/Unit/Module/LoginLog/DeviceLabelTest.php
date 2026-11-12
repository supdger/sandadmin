<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\LoginLog;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\LoginLog\DeviceLabel;

/** M2 登录设备名：User-Agent 折成「系统上的浏览器」，命令行工具给「用工具访问」，认不出给「其他设备」。 */
final class DeviceLabelTest extends TestCase
{
    public function testDesktopChromeOnWindows(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36';

        self::assertSame('Windows 电脑 上的 Chrome', DeviceLabel::of($ua));
    }

    public function testSafariOnIphone(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

        self::assertSame('iPhone 上的 Safari', DeviceLabel::of($ua));
    }

    public function testEdgeOnMac(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 Chrome/126.0 Edg/126.0';

        self::assertSame('Mac 上的 Edge', DeviceLabel::of($ua));
    }

    public function testUnrecognizedAgentGivesFallback(): void
    {
        self::assertSame('其他设备', DeviceLabel::of(null));
        self::assertSame('其他设备', DeviceLabel::of(''));
        self::assertSame('其他设备', DeviceLabel::of('###乱串###'));
        // 只认出系统或只认出浏览器时也拼得出来
        self::assertSame('Linux 电脑', DeviceLabel::of('CustomAgent Linux'));
    }

    public function testCommandLineToolsGiveToolLabel(): void
    {
        self::assertSame('用工具访问', DeviceLabel::of('curl/8.4.0'));
        self::assertSame('用工具访问', DeviceLabel::of('Wget/1.21.4'));
        self::assertSame('用工具访问', DeviceLabel::of('python-requests/2.31.0'));
        self::assertSame('用工具访问', DeviceLabel::of('HTTPie/3.2.2'));
    }
}
