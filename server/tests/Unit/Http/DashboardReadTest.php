<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Sandadmin\Http\DashboardRead;

final class DashboardReadTest extends TestCase
{
    public function testEmptyListsAreArrays(): void
    {
        $onboarding = DashboardRead::onboarding();
        self::assertTrue($onboarding['done']);
        self::assertSame([], $onboarding['steps']);

        self::assertSame([], DashboardRead::summary()['cards']);
        self::assertSame([], DashboardRead::todos()['items']);
        self::assertSame(0, DashboardRead::todos()['total']);

        $page = DashboardRead::allTodos(2, 20);
        self::assertSame([], $page['items']);
        self::assertSame(0, $page['total']);
        self::assertSame(2, $page['page']);
        self::assertSame(20, $page['page_size']);

        self::assertSame([], DashboardRead::charts()['charts']);
        self::assertSame([], DashboardRead::activities()['items']);
        self::assertSame([], DashboardRead::notices()['items']);

        $shortcuts = DashboardRead::shortcuts();
        self::assertSame('auto', $shortcuts['mode']);
        self::assertSame([], $shortcuts['items']);
    }
}
