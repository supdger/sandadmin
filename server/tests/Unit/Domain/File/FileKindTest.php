<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\File\FileKind;

final class FileKindTest extends TestCase
{
    public function testImageMimeWins(): void
    {
        self::assertSame(FileKind::Image, FileKind::fromMediaType('image/png', 'png'));
        self::assertSame(FileKind::Image, FileKind::fromMediaType('image/jpeg', ''));
    }

    public function testSheetByMimeAndExtension(): void
    {
        self::assertSame(
            FileKind::Sheet,
            FileKind::fromMediaType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx')
        );
        self::assertSame(FileKind::Sheet, FileKind::fromMediaType('text/csv', 'csv'));
    }

    public function testArchiveByMimeAndExtension(): void
    {
        self::assertSame(FileKind::Archive, FileKind::fromMediaType('application/zip', 'zip'));
        self::assertSame(FileKind::Archive, FileKind::fromMediaType('application/x-gzip', 'gz'));
    }

    public function testDocumentByMimeAndExtension(): void
    {
        self::assertSame(FileKind::Document, FileKind::fromMediaType('application/pdf', 'pdf'));
        self::assertSame(FileKind::Document, FileKind::fromMediaType('text/plain', 'txt'));
    }

    public function testUnknownFallsToOther(): void
    {
        self::assertSame(FileKind::Other, FileKind::fromMediaType('application/octet-stream', 'bin'));
        self::assertSame(FileKind::Other, FileKind::fromMediaType('application/x-msdownload', 'exe'));
    }
}
