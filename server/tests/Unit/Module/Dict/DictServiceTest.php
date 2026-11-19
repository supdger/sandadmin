<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Module\Dict;

use PHPUnit\Framework\TestCase;
use Sandadmin\Module\Dict\DictException;
use Sandadmin\Module\Dict\DictService;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Tests\Support\Disconnected;

/**
 * 选项服务的入库前校验：name/remark 的字段校验发生在第一次取数之前，
 * 用免构造实例覆盖这条纯校验链（不建任何库连接，触达连接属性即以
 * Error 失败，等于顺带证明顺序）。落库行为见 Integration 同名测试。
 */
final class DictServiceTest extends TestCase
{
    private OrgActor $actor;

    protected function setUp(): void
    {
        $this->actor = new OrgActor(7, '测试管理员', null, true, ['*']);
    }

    private function dicts(): DictService
    {
        return Disconnected::make(DictService::class);
    }

    public function testCreateTypeValidatesInput(): void
    {
        try {
            $this->dicts()->createType([], $this->actor, 'r', null, null);
            self::fail('缺 name 应抛异常');
        } catch (OrgException $e) {
            self::assertSame(422, $e->status);
            self::assertArrayHasKey('name', $e->fields);
        }
        try {
            $this->dicts()->createType(['name' => str_repeat('很', 21)], $this->actor, 'r', null, null);
            self::fail('超长 name 应抛异常');
        } catch (DictException $e) {
            self::assertSame(422, $e->status);
            self::assertArrayHasKey('name', $e->fields);
        }
        try {
            $this->dicts()->createType(['name' => '组', 'remark' => str_repeat('长', 101)], $this->actor, 'r', null, null);
            self::fail('超长 remark 应抛异常');
        } catch (DictException $e) {
            self::assertArrayHasKey('remark', $e->fields);
        }
    }
}
