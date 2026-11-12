<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Announcement;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Announcement\ContentSanitizer;

/** 富文本白名单净化：标签/属性/协议三面的放行与剥离。 */
final class ContentSanitizerTest extends TestCase
{
    public function testKeepsWhitelistedTags(): void
    {
        $html = '<p>你好<strong>加粗</strong><em>斜体</em></p><ul><li>项</li></ul><table><thead><tr><th>h</th></tr></thead><tbody><tr><td>d</td></tr></tbody></table>';
        $out = ContentSanitizer::clean($html);
        self::assertStringContainsString('<strong>加粗</strong>', $out);
        self::assertStringContainsString('<td>d</td>', $out);
    }

    public function testDropsScriptAndEventHandlers(): void
    {
        $html = '<p onclick="x()">正文</p><script>alert(1)</script><div onload="x()">a</div>';
        $out = ContentSanitizer::clean($html);
        self::assertStringNotContainsString('script', strtolower($out));
        self::assertStringNotContainsString('alert', $out);
        self::assertStringNotContainsString('onclick', strtolower($out));
        self::assertStringNotContainsString('onload', strtolower($out));
        self::assertStringContainsString('正文', $out);
    }

    public function testStripsStyleClassAndUnlistedAttrs(): void
    {
        $html = '<p style="color:red" class="x">a</p><img src="https://a/1.png" alt="图" style="x" onerror="y()">';
        $out = ContentSanitizer::clean($html);
        self::assertStringNotContainsString('style=', $out);
        self::assertStringNotContainsString('class=', $out);
        self::assertStringNotContainsString('onerror', strtolower($out));
        self::assertStringContainsString('alt="图"', $out);
    }

    public function testBlocksJavascriptAndDataUrls(): void
    {
        $out = ContentSanitizer::clean('<a href="javascript:alert(1)">链</a><img src="data:image/png;base64,x">');
        self::assertStringNotContainsString('javascript:', strtolower($out));
        self::assertStringNotContainsString('data:image', strtolower($out));
        self::assertStringContainsString('链', $out);
    }

    public function testMailtoAndRelativeAllowedAndBlankTargetGetsRel(): void
    {
        $out = ContentSanitizer::clean('<a href="mailto:a@b.c" target="_blank">m</a><img src="/files/1.png">');
        self::assertStringContainsString('mailto:a@b.c', $out);
        self::assertStringContainsString('noopener noreferrer', $out);
        self::assertStringContainsString('/files/1.png', $out);
    }
}
