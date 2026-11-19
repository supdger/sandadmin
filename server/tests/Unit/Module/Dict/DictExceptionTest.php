<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Dict;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Dict\DictException;

/** 选项模块异常工厂：状态码、契约错误码与字段提示一一对应。 */
final class DictExceptionTest extends TestCase
{
    public function testInvalidKeepsFields(): void
    {
        $e = DictException::invalid(['name' => ['组名称最多 20 个字']]);
        self::assertSame(422, $e->status);
        self::assertSame(ErrorCodeRegistry::VALIDATION_FAILED, $e->errorCode);
        self::assertSame(['name' => ['组名称最多 20 个字']], $e->fields);
    }

    public function testInvalidDefaultMessage(): void
    {
        $e = DictException::invalid([]);
        self::assertSame(422, $e->status);
        self::assertSame('请求内容有误，请按提示修改', $e->getMessage());
        self::assertSame([], $e->fields);
    }

    public function testUnauthenticated(): void
    {
        $e = DictException::unauthenticated();
        self::assertSame(401, $e->status);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, $e->errorCode);
        self::assertSame('请先登录', $e->getMessage());
    }

    public function testDenied(): void
    {
        $e = DictException::denied();
        self::assertSame(403, $e->status);
        self::assertSame(ErrorCodeRegistry::PERMISSION_DENIED, $e->errorCode);
    }

    public function testTypeNotFound(): void
    {
        $e = DictException::typeNotFound();
        self::assertSame(404, $e->status);
        self::assertSame(ErrorCodeRegistry::NOT_FOUND, $e->errorCode);
    }

    public function testItemNotFound(): void
    {
        $e = DictException::itemNotFound();
        self::assertSame(404, $e->status);
        self::assertSame(ErrorCodeRegistry::NOT_FOUND, $e->errorCode);
    }

    public function testTypeTaken(): void
    {
        $e = DictException::typeTaken();
        self::assertSame(409, $e->status);
        self::assertSame(ErrorCodeRegistry::DICT_TYPE_TAKEN, $e->errorCode);
        self::assertArrayHasKey('name', $e->fields);
    }

    public function testItemTaken(): void
    {
        $e = DictException::itemTaken();
        self::assertSame(409, $e->status);
        self::assertSame(ErrorCodeRegistry::DICT_ITEM_TAKEN, $e->errorCode);
        self::assertArrayHasKey('label', $e->fields);
    }
}
