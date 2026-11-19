<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Dict;

use PHPUnit\Framework\TestCase;
use Sandadmin\Contract\Generated\ErrorCodeRegistry;
use Sandadmin\Module\Dict\DictException;

/** 选项管理异常的工厂：状态码、错误码与字段级提示随工厂固定。 */
final class DictExceptionTest extends TestCase
{
    public function testInvalidCarriesFieldsAnd422(): void
    {
        $error = DictException::invalid(['name' => ['组名称最多 20 个字']]);

        self::assertSame(422, $error->status);
        self::assertSame(ErrorCodeRegistry::VALIDATION_FAILED, $error->errorCode);
        self::assertSame(['name' => ['组名称最多 20 个字']], $error->fields);
    }

    public function testNotFoundFactoriesUse404(): void
    {
        self::assertSame(404, DictException::typeNotFound()->status);
        self::assertSame(404, DictException::itemNotFound()->status);
        self::assertSame(ErrorCodeRegistry::NOT_FOUND, DictException::typeNotFound()->errorCode);
        self::assertSame(ErrorCodeRegistry::NOT_FOUND, DictException::itemNotFound()->errorCode);
    }

    public function testTakenFactoriesUse409WithField(): void
    {
        $type = DictException::typeTaken();
        self::assertSame(409, $type->status);
        self::assertSame(ErrorCodeRegistry::DICT_TYPE_TAKEN, $type->errorCode);
        self::assertArrayHasKey('name', $type->fields);

        $item = DictException::itemTaken();
        self::assertSame(409, $item->status);
        self::assertSame(ErrorCodeRegistry::DICT_ITEM_TAKEN, $item->errorCode);
        self::assertArrayHasKey('label', $item->fields);
    }

    public function testAuthFactories(): void
    {
        self::assertSame(401, DictException::unauthenticated()->status);
        self::assertSame(ErrorCodeRegistry::UNAUTHENTICATED, DictException::unauthenticated()->errorCode);
        self::assertSame(403, DictException::denied()->status);
        self::assertSame(ErrorCodeRegistry::PERMISSION_DENIED, DictException::denied()->errorCode);
    }
}
