<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\BoundedCopier;
use Sandadmin\Application\File\PendingStream;
use Sandadmin\Domain\File\FileException;

final class BoundedCopierTest extends TestCase
{
    public function testCopiesStreamAndComputesSha256(): void
    {
        $content = random_bytes(20000);
        $stream = fopen('php://temp', 'rb+');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        $pending = (new BoundedCopier())->copy($stream, 1048576);
        try {
            self::assertInstanceOf(PendingStream::class, $pending);
            self::assertSame(strlen($content), $pending->size);
            self::assertSame(hash('sha256', $content), $pending->sha256);
            $copied = stream_get_contents($pending->stream());
            self::assertSame($content, $copied);
        } finally {
            $pending->close();
            fclose($stream);
        }
    }

    public function testRejectsOversize(): void
    {
        $stream = fopen('php://temp', 'rb+');
        self::assertIsResource($stream);
        fwrite($stream, str_repeat('x', 100));
        rewind($stream);

        try {
            $this->expectException(FileException::class);
            (new BoundedCopier())->copy($stream, 50);
        } finally {
            fclose($stream);
        }
    }

    public function testRejectsEmpty(): void
    {
        $stream = fopen('php://temp', 'rb+');
        self::assertIsResource($stream);

        try {
            $this->expectException(FileException::class);
            (new BoundedCopier())->copy($stream, 1024);
        } finally {
            fclose($stream);
        }
    }

    public function testRejectsClosedStream(): void
    {
        // 已关闭的流静态上仍是 resource，运行时 is_resource 为 false
        $stream = fopen('php://temp', 'rb+');
        self::assertIsResource($stream);
        fclose($stream);

        $this->expectException(FileException::class);
        (new BoundedCopier())->copy($stream, 1024);
    }
}
