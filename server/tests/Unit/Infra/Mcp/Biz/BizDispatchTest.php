<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Biz;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\CallContext;
use Sandadmin\Http\BizKernel;
use Sandadmin\Infra\Mcp\Biz\BizDispatch;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgException;

/** 派发映射冒烟：操作键真的落到对应业务服务（连接层打桩）。 */
final class BizDispatchTest extends TestCase
{
    public function testDeptsTreeDispatches(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $reply = BizDispatch::call(
            BizKernel::fromConnection($connection),
            $this->actor(),
            'depts_tree',
            ['idempotency_key' => 'ignored-on-read'],
            $this->context(),
        );

        self::assertTrue($reply->succeeded());
        self::assertSame(['items' => []], $reply->data);
    }

    public function testDictsIndexDispatches(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $reply = BizDispatch::call(
            BizKernel::fromConnection($connection),
            $this->actor(),
            'dicts_index',
            [],
            $this->context(),
        );

        self::assertTrue($reply->succeeded());
        self::assertSame(['items' => []], $reply->data);
    }

    public function testPermissionTreeNeedsNoDb(): void
    {
        $reply = BizDispatch::call(
            BizKernel::fromConnection($this->createMock(Connection::class)),
            $this->actor(),
            'permission_tree',
            [],
            $this->context(),
        );

        self::assertTrue($reply->succeeded());
        self::assertIsArray($reply->data);
    }

    public function testInvalidDeptStatusThrows(): void
    {
        $this->expectException(OrgException::class);
        BizDispatch::call(
            BizKernel::fromConnection($this->createMock(Connection::class)),
            $this->actor(),
            'depts_tree',
            ['status' => 'bogus'],
            $this->context(),
        );
    }

    private function actor(): OrgActor
    {
        return new OrgActor(1, '测试账号', null, true, ['*']);
    }

    private function context(): CallContext
    {
        return new CallContext('req_dispatch', '127.0.0.1', 'mcp');
    }
}
