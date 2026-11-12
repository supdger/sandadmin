<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Domain\File;

use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\File\FileUsageState;

final class FileUsageStateTest extends TestCase
{
    public function testResolveUsedWhenHasUsage(): void
    {
        self::assertSame(FileUsageState::Used, FileUsageState::resolve(true, true));
        self::assertSame(FileUsageState::Used, FileUsageState::resolve(true, false));
    }

    public function testResolveUnusedWhenTrackedWithoutUsage(): void
    {
        self::assertSame(FileUsageState::Unused, FileUsageState::resolve(false, true));
    }

    public function testResolveUnknownWhenUntrackedWithoutUsage(): void
    {
        self::assertSame(FileUsageState::Unknown, FileUsageState::resolve(false, false));
    }

    public function testValuesMatchContract(): void
    {
        self::assertSame('used', FileUsageState::Used->value);
        self::assertSame('unused', FileUsageState::Unused->value);
        self::assertSame('unknown', FileUsageState::Unknown->value);
    }
}
