<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Contract;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\KernelApi\KernelApiVersion;

/** kernel_api 版本与语义化区间判定：宿主 CURRENT 落在 [min,max] 且与 min 同主版本才算兼容。 */
final class KernelApiVersionTest extends TestCase
{
    public function testCurrentIsSemver(): void
    {
        self::assertTrue(KernelApiVersion::isSemver(KernelApiVersion::CURRENT));
    }

    public function testIsSemverAcceptsStrictVersions(): void
    {
        self::assertTrue(KernelApiVersion::isSemver('0.0.0'));
        self::assertTrue(KernelApiVersion::isSemver('1.9.9'));
        self::assertTrue(KernelApiVersion::isSemver('10.20.30'));
    }

    public function testIsSemverRejectsLooseForms(): void
    {
        self::assertFalse(KernelApiVersion::isSemver('1.0'));
        self::assertFalse(KernelApiVersion::isSemver('1.0.0.0'));
        self::assertFalse(KernelApiVersion::isSemver('v1.0.0'));
        self::assertFalse(KernelApiVersion::isSemver('1.0.0-beta'));
        self::assertFalse(KernelApiVersion::isSemver('01.0.0'));
        self::assertFalse(KernelApiVersion::isSemver(''));
    }

    public function testCompareOrdersNumericParts(): void
    {
        self::assertSame(0, KernelApiVersion::compare('1.0.0', '1.0.0'));
        self::assertSame(-1, KernelApiVersion::compare('1.0.0', '1.0.1'));
        self::assertSame(1, KernelApiVersion::compare('1.10.0', '1.2.0'));
        self::assertSame(-1, KernelApiVersion::compare('0.9.9', '1.0.0'));
    }

    public function testSatisfiesInsideRangeSameMajor(): void
    {
        self::assertTrue(KernelApiVersion::satisfies('1.0.0', '1.9.9'));
        // 区间跨主版本时 major(CURRENT) 对不上 min，按不兼容处理
        self::assertFalse(KernelApiVersion::satisfies('0.5.0', '1.9.9'));
    }

    public function testSatisfiesRejectsOutsideRange(): void
    {
        self::assertFalse(KernelApiVersion::satisfies('1.0.1', '1.9.9'));
        self::assertFalse(KernelApiVersion::satisfies('0.9.0', '0.9.9'));
        self::assertFalse(KernelApiVersion::satisfies('2.0.0', '2.9.9'));
    }

    public function testSatisfiesRejectsInvalidBounds(): void
    {
        self::assertFalse(KernelApiVersion::satisfies('abc', '1.9.9'));
        self::assertFalse(KernelApiVersion::satisfies('1.0.0', 'xyz'));
    }
}
