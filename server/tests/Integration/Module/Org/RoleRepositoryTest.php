<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

/** 角色仓储：「指定集合只剩本部门」判定与降级为 self。需要 SAND_TEST_DB_URL（见 OrgCase）。 */
final class RoleRepositoryTest extends OrgCase
{
    public function testListSolelyScopedTo(): void
    {
        $deptId = $this->addDept('技术部');
        $otherDeptId = $this->addDept('市场部');

        $soleRole = $this->addRole('只看技术部', 'custom');
        $this->scopeRoleTo($soleRole, $deptId);
        $multiRole = $this->addRole('跨部门角色', 'custom');
        $this->scopeRoleTo($multiRole, $deptId);
        $this->scopeRoleTo($multiRole, $otherDeptId);
        $emptyRole = $this->addRole('空指定集', 'custom');
        $this->scopeRoleTo($emptyRole, $otherDeptId);
        $this->addRole('本部门及下级', 'dept_and_child');

        self::assertSame(
            [['id' => $soleRole, 'name' => '只看技术部']],
            $this->roles->listSolelyScopedTo($deptId),
        );
        self::assertSame(
            [['id' => $emptyRole, 'name' => '空指定集']],
            $this->roles->listSolelyScopedTo($otherDeptId),
        );
    }

    public function testListSolelyScopedToNoMatch(): void
    {
        $deptId = $this->addDept('技术部');
        $this->addRole('全数据', 'all');

        self::assertSame([], $this->roles->listSolelyScopedTo($deptId));
    }

    public function testScopeToSelf(): void
    {
        $deptId = $this->addDept('技术部');
        $roleId = $this->addRole('只看技术部', 'custom');
        $this->scopeRoleTo($roleId, $deptId);
        $otherRoleId = $this->addRole('本部门', 'dept');

        self::assertSame(1, $this->roles->scopeToSelf([$roleId]));

        $role = $this->roles->find($roleId);
        self::assertNotNull($role);
        self::assertSame('self', $role->dataScope);
        self::assertSame(2, $role->authzVersion);

        $other = $this->roles->find($otherRoleId);
        self::assertNotNull($other);
        self::assertSame('dept', $other->dataScope);
        self::assertSame(1, $other->authzVersion);
    }

    public function testScopeToSelfEmptyList(): void
    {
        self::assertSame(0, $this->roles->scopeToSelf([]));
    }
}
