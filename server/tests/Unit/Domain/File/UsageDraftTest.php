<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\File\FileException;
use Sandadmin\Domain\File\UsageDraft;

final class UsageDraftTest extends TestCase
{
    public function testParseSplitsOnFirstColon(): void
    {
        $draft = UsageDraft::parse('article:2026:11');
        self::assertSame('article', $draft->subjectType);
        self::assertSame('2026:11', $draft->subjectId);
        self::assertSame('article:2026:11', $draft->label);
    }

    public function testParseTrimsWhitespace(): void
    {
        $draft = UsageDraft::parse('  announcement:abc-123  ');
        self::assertSame('announcement', $draft->subjectType);
        self::assertSame('abc-123', $draft->subjectId);
    }

    public function testParseRejectsMissingColon(): void
    {
        $this->expectException(FileException::class);
        UsageDraft::parse('article');
    }

    public function testParseRejectsEmptyType(): void
    {
        $this->expectException(FileException::class);
        UsageDraft::parse(':123');
    }

    public function testParseRejectsEmptyId(): void
    {
        $this->expectException(FileException::class);
        UsageDraft::parse('article:');
    }

    public function testParseRejectsOverlongLabel(): void
    {
        $this->expectException(FileException::class);
        UsageDraft::parse(str_repeat('a', 130) . ':1');
    }
}
