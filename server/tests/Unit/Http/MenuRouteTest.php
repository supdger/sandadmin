<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;

/** 菜单写入和我的资料必须挂在路由上，不能只靠前端记忆。 */
final class MenuRouteTest extends TestCase
{
    public function testMenuWriteAndProfileRoutesExist(): void
    {
        $route = file_get_contents(dirname(__DIR__, 3) . '/config/route.php');
        self::assertIsString($route);
        foreach ([
            "Route::get('/api/admin/v1/menus/tree'",
            "Route::post('/api/admin/v1/menus/groups'",
            "Route::post('/api/admin/v1/menus/links'",
            "Route::patch('/api/admin/v1/menus/{menu_key}'",
            "Route::delete('/api/admin/v1/menus/{menu_key}'",
            "Route::get('/api/admin/v1/me/profile'",
            "Route::put('/api/admin/v1/me/profile'",
        ] as $line) {
            self::assertStringContainsString($line, $route);
        }
    }
}
