<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Org;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Org\DisableCheckAccess;

final class DisableCheckAccessTest extends TestCase
{
    public function testAllowsUpdateOrDelete(): void
    {
        self::assertTrue(DisableCheckAccess::allowed([DisableCheckAccess::UPDATE]));
        self::assertTrue(DisableCheckAccess::allowed([DisableCheckAccess::DELETE]));
        self::assertTrue(DisableCheckAccess::allowed([
            'system:user:index',
            DisableCheckAccess::UPDATE,
            DisableCheckAccess::DELETE,
        ]));
    }

    public function testRejectsWithoutUpdateOrDelete(): void
    {
        self::assertFalse(DisableCheckAccess::allowed([]));
        self::assertFalse(DisableCheckAccess::allowed(['system:user:index']));
        self::assertFalse(DisableCheckAccess::allowed(['system:user:create']));
    }
}
