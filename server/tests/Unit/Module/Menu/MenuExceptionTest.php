<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Menu;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Menu\MenuException;

/** 菜单模块异常工厂：状态码、契约错误码与字段提示一一对应。 */
final class MenuExceptionTest extends TestCase
{
    public function testInvalidKeepsFields(): void
    {
        $e = MenuException::invalid(['title' => ['名称不能为空']]);
        self::assertSame(422, $e->status);
        self::assertSame(ErrorCodeRegistry::VALIDATION_FAILED, $e->errorCode);
        self::assertSame(['title' => ['名称不能为空']], $e->fields);
    }

    public function testNotFound(): void
    {
        $e = MenuException::notFound();
        self::assertSame(404, $e->status);
        self::assertSame(ErrorCodeRegistry::MENU_NOT_FOUND, $e->errorCode);
    }

    public function testLocked(): void
    {
        $e = MenuException::locked('内置菜单不能改');
        self::assertSame(409, $e->status);
        self::assertSame(ErrorCodeRegistry::MENU_LOCKED, $e->errorCode);
        self::assertSame('内置菜单不能改', $e->getMessage());
    }

    public function testBadUrl(): void
    {
        $e = MenuException::badUrl();
        self::assertSame(422, $e->status);
        self::assertSame(ErrorCodeRegistry::MENU_URL_INVALID, $e->errorCode);
        self::assertArrayHasKey('url', $e->fields);
    }

    public function testTooDeep(): void
    {
        $e = MenuException::tooDeep();
        self::assertSame(422, $e->status);
        self::assertSame(ErrorCodeRegistry::MENU_DEPTH_EXCEEDED, $e->errorCode);
        self::assertArrayHasKey('parent_key', $e->fields);
    }

    public function testStale(): void
    {
        $e = MenuException::stale();
        self::assertSame(409, $e->status);
        self::assertSame(ErrorCodeRegistry::STALE_VERSION, $e->errorCode);
    }
}
