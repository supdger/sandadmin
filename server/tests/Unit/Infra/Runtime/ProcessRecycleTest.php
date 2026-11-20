<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Runtime;

use PHPUnit\Framework\TestCase;
use Sandadmin\Infra\Runtime\ProcessRecycle;

final class ProcessRecycleTest extends TestCase
{
    public function testRejectsNegativeThresholds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProcessRecycle(-1, 0);
    }

    public function testFromConfigFallsBackToEightyPercentOf256MiB(): void
    {
        // 测试进程没有装载 config/：上限缺省走 256MiB 兜底，水位=80%
        $recycle = ProcessRecycle::fromConfig();
        $prop = new \ReflectionProperty(ProcessRecycle::class, 'memoryWatermarkBytes');

        self::assertSame(intdiv(ProcessRecycle::FALLBACK_LIMIT_BYTES * 4, 5), $prop->getValue($recycle));
    }

    public function testZeroThresholdsDisableBothRecycles(): void
    {
        // 阈值全 0：noteRequest/checkMemory 直接返回，不触发任何信号路径
        $recycle = new ProcessRecycle(0, 0);
        $recycle->noteRequest();
        $recycle->checkMemory();
        $this->addToAssertionCount(1);
    }
}
