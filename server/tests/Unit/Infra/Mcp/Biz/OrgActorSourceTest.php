<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Unit\Infra\Mcp\Biz;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Infra\Mcp\Biz\OrgActorSource;

/** MCP 身份 → OrgActor：账号权限与凭据 scope 的收窄判定。 */
final class OrgActorSourceTest extends TestCase
{
    public function testMissingUserReturnsNull(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $actor = (new OrgActorSource())->resolve($this->identity(['*']), $connection);
        self::assertNull($actor);
    }

    public function testSuperUserWithFullScopeKeepsSuper(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 7, 'nickname' => '管理员', 'dept_id' => 3, 'is_super' => true,
        ]);
        $actor = (new OrgActorSource())->resolve($this->identity(['*']), $connection);
        self::assertNotNull($actor);
        self::assertTrue($actor->isSuper);
        self::assertSame(['*'], $actor->permissions);
        self::assertSame(7, $actor->userId);
        self::assertSame(3, $actor->deptId);
    }

    public function testSuperUserWithNarrowScopeLosesSuper(): void
    {
        // scope 只能缩小：令牌收窄后账号虽是超管，actor 不得再按 is_super 放行
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 7, 'nickname' => '管理员', 'dept_id' => null, 'is_super' => 't',
        ]);
        $actor = (new OrgActorSource())->resolve($this->identity(['system:menu:index']), $connection);
        self::assertNotNull($actor);
        self::assertFalse($actor->isSuper);
        self::assertSame(['system:menu:index'], $actor->permissions);
        self::assertTrue($actor->can('system:menu:index'));
        self::assertFalse($actor->can('system:user:create'));
    }

    public function testNormalUserLoadsRoleCodes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 8, 'nickname' => '运营', 'dept_id' => null, 'is_super' => false,
        ]);
        $connection->method('fetchFirstColumn')->willReturn(['system:dict:index', 'system:dict:update']);
        $actor = (new OrgActorSource())->resolve($this->identity(['*']), $connection);
        self::assertNotNull($actor);
        self::assertFalse($actor->isSuper);
        self::assertSame(['system:dict:index', 'system:dict:update'], $actor->permissions);
    }

    public function testNormalUserScopeIntersectsAccountCodes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 8, 'nickname' => '运营', 'dept_id' => null, 'is_super' => 0,
        ]);
        $connection->method('fetchFirstColumn')->willReturn(['system:dict:index', 'system:dict:update']);
        $actor = (new OrgActorSource())->resolve($this->identity(['system:dict:index', 'system:menu:index']), $connection);
        self::assertNotNull($actor);
        // 交集：scope 里的 system:menu:index 不是账号权限，进不来
        self::assertSame(['system:dict:index'], $actor->permissions);
    }

    /** @param list<string> $permissions */
    private function identity(array $permissions): McpIdentity
    {
        return new McpIdentity('admin', 'dev-token', ['mcp:dev'], $permissions);
    }
}
