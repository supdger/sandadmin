<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Application\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Application\File\StorageConfig;
use Sandadmin\Domain\File\FileException;

final class StorageConfigTest extends TestCase
{
    public function testFromArrayNormalizes(): void
    {
        $config = StorageConfig::fromArray([
            'root' => '/tmp/files',
            'max_bytes' => 1024,
            'allowed_mime_types' => ['IMAGE/PNG', 'Application/PDF'],
            'allowed_extensions' => ['PNG', 'PDF'],
        ]);
        self::assertSame('/tmp/files', $config->root);
        self::assertSame(1024, $config->maxBytes);
        self::assertSame(['image/png', 'application/pdf'], $config->allowedMimeTypes);
        self::assertSame(['png', 'pdf'], $config->allowedExtensions);
    }

    public function testRejectsMissingKeys(): void
    {
        $this->expectException(FileException::class);
        StorageConfig::fromArray(['root' => '/tmp']);
    }

    public function testRejectsInvalidMime(): void
    {
        $this->expectException(FileException::class);
        StorageConfig::fromArray([
            'root' => '/tmp',
            'max_bytes' => 1,
            'allowed_mime_types' => ['not a mime'],
            'allowed_extensions' => ['png'],
        ]);
    }

    public function testDefaultLimits(): void
    {
        self::assertSame(104857600, StorageConfig::DEFAULT_MAX_BYTES);
        self::assertContains('image/png', StorageConfig::defaultAllowedMimeTypes());
        self::assertContains('pdf', StorageConfig::defaultAllowedExtensions());
    }
}
