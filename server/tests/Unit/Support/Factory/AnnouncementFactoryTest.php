<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Support\Factory;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Announcement\AnnouncementScope;
use Sandadmin\Module\Announcement\AnnouncementState;
use Sandadmin\Tests\Support\Factory\AnnouncementFactory;

/** 公告工厂：枚举默认值、范围覆盖与派生数组透传。 */
final class AnnouncementFactoryTest extends TestCase
{
    public function testDefaultsAreDraftForAllDepts(): void
    {
        $notice = AnnouncementFactory::make();
        self::assertSame(AnnouncementState::Draft, $notice->state);
        self::assertSame(AnnouncementScope::All, $notice->scope);
        self::assertSame([], $notice->deptIds);
    }

    public function testDeptScopeCarriesIdsAndNames(): void
    {
        $notice = AnnouncementFactory::make(
            scope: AnnouncementScope::Dept,
            deptIds: [2, 3],
            deptNames: ['技术部', '市场部'],
        );
        self::assertSame([2, 3], $notice->deptIds);
        self::assertSame(['技术部', '市场部'], $notice->deptNames);
    }
}
