<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;

/**
 * GET /users/{user_id}/disable-check 的权限检查必须走 DisableCheckAccess，
 * 不再在控制器里散写权限码。
 */
final class DisableCheckRouteTest extends TestCase
{
    public function testControllerChecksViaDisableCheckAccess(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Http/OrgController.php');
        self::assertIsString($source);
        self::assertStringContainsString('DisableCheckAccess::allowed(', $source);
        self::assertStringNotContainsString("'system:user:update', 'system:user:delete'", $source);
    }
}
