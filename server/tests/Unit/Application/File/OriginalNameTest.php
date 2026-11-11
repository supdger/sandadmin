<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\OriginalName;

final class OriginalNameTest extends TestCase
{
    public function testStripsPathComponents(): void
    {
        self::assertSame('a.png', OriginalName::sanitize('../../etc/a.png'));
        self::assertSame('b.txt', OriginalName::sanitize('C:\\fakepath\\b.txt'));
    }

    public function testRemovesControlCharacters(): void
    {
        self::assertSame('ab.txt', OriginalName::sanitize("a\x00b\x1F.txt"));
    }

    public function testEmptyBecomesFile(): void
    {
        self::assertSame('file', OriginalName::sanitize('   '));
        self::assertSame('file', OriginalName::sanitize('..'));
        self::assertSame('file', OriginalName::sanitize("\x01\x02"));
    }

    public function testTruncatesTo255Chars(): void
    {
        $name = str_repeat('长', 300) . '.png';
        $result = OriginalName::sanitize($name);
        self::assertLessThanOrEqual(255, mb_strlen($result));
    }

    public function testExtensionLowercase(): void
    {
        self::assertSame('png', OriginalName::extension('A.PNG'));
        self::assertSame('', OriginalName::extension('noext'));
        self::assertSame('', OriginalName::extension('bad.ext!'));
        self::assertSame('', OriginalName::extension('.hidden'));
    }
}
