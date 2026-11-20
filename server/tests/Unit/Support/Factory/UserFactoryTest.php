<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Support\Factory;

use PHPUnit\Framework\TestCase;
use Sandadmin\Tests\Support\Factory\DeptFactory;
use Sandadmin\Tests\Support\Factory\RoleFactory;
use Sandadmin\Tests\Support\Factory\UserFactory;

/** 内存工厂：默认值可用、具名覆盖生效、不触碰数据库。 */
final class UserFactoryTest extends TestCase
{
    public function testDefaultsProduceUsableEntity(): void
    {
        $user = UserFactory::make();
        self::assertSame(1, $user->id);
        self::assertSame('u_test', $user->username);
        self::assertSame(1, $user->status);
        self::assertNull($user->deleteTime);
    }

    public function testOverridesApplyToEntity(): void
    {
        $user = UserFactory::make(id: 42, isSuper: true, nickname: '张三');
        self::assertSame(42, $user->id);
        self::assertTrue($user->isSuper);
        self::assertSame('张三', $user->nickname);
    }

    public function testSiblingFactoriesBuild(): void
    {
        $dept = DeptFactory::make(id: 7, name: '技术部');
        $role = RoleFactory::make(code: 'tech_lead', dataScope: 'dept');
        self::assertSame('技术部', $dept->name);
        self::assertSame('dept', $role->dataScope);
    }
}
