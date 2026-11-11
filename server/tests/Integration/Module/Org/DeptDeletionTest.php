<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

use Sandadmin\Module\Org\DeptDeletion;
use Sandadmin\Module\Org\Exception\DeptNotEmptyException;
use Sandadmin\Module\Org\Exception\DeptNotFoundException;

/** 删除部门联动（J7）：预检口径、同事务复检、受影响角色降级。需要 SAND_TEST_DB_URL（见 OrgCase）。 */
final class DeptDeletionTest extends OrgCase
{
    private DeptDeletion $deletion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deletion = new DeptDeletion($this->conn, $this->depts, $this->users, $this->roles);
    }

    public function testCheckReportsCountsAndAffectedRoles(): void
    {
        $deptId = $this->addDept('技术部');
        $this->addDept('后端组', $deptId);
        $otherDeptId = $this->addDept('市场部');
        $this->addUser($deptId, '13800000040');
        $this->addUser($deptId, '13800000041');

        $soleRole = $this->addRole('只看技术部', 'custom');
        $this->scopeRoleTo($soleRole, $deptId);
        $multiRole = $this->addRole('跨部门角色', 'custom');
        $this->scopeRoleTo($multiRole, $deptId);
        $this->scopeRoleTo($multiRole, $otherDeptId);
        $this->addRole('全数据', 'all');

        $check = $this->deletion->check($deptId);

        self::assertSame(2, $check->userCount);
        self::assertSame(1, $check->childCount);
        self::assertSame([['id' => $soleRole, 'name' => '只看技术部']], $check->affectedRoles);
        self::assertSame(
            [
                'user_count' => 2,
                'child_count' => 1,
                'affected_roles' => [['id' => $soleRole, 'name' => '只看技术部']],
            ],
            $check->jsonSerialize(),
        );
    }

    public function testCheckMissingDeptThrows(): void
    {
        $this->expectException(DeptNotFoundException::class);
        $this->deletion->check(999999);
    }

    public function testDeleteScopesSolelyScopedRoles(): void
    {
        $deptId = $this->addDept('技术部');
        $otherDeptId = $this->addDept('市场部');

        $soleRole = $this->addRole('只看技术部', 'custom');
        $this->scopeRoleTo($soleRole, $deptId);
        $multiRole = $this->addRole('跨部门角色', 'custom');
        $this->scopeRoleTo($multiRole, $deptId);
        $this->scopeRoleTo($multiRole, $otherDeptId);

        $this->deletion->delete($deptId);

        self::assertNull($this->depts->find($deptId));
        self::assertSame([$otherDeptId], $this->roleDeptIds($multiRole));

        $role = $this->roles->find($soleRole);
        self::assertNotNull($role);
        self::assertSame('self', $role->dataScope);
        self::assertSame(2, $role->authzVersion);

        $multi = $this->roles->find($multiRole);
        self::assertNotNull($multi);
        self::assertSame('custom', $multi->dataScope);
        self::assertSame(1, $multi->authzVersion);
    }

    public function testDeleteWithUsersThrowsAndKeepsDept(): void
    {
        $deptId = $this->addDept('技术部');
        $this->addUser($deptId, '13800000042');
        $roleId = $this->addRole('只看技术部', 'custom');
        $this->scopeRoleTo($roleId, $deptId);

        try {
            $this->deletion->delete($deptId);
            self::fail('部门下有用户时应当抛 DeptNotEmptyException');
        } catch (DeptNotEmptyException) {
        }

        self::assertNotNull($this->depts->find($deptId));
        self::assertSame([$deptId], $this->roleDeptIds($roleId));
        $role = $this->roles->find($roleId);
        self::assertNotNull($role);
        self::assertSame('custom', $role->dataScope);
    }

    public function testDeleteWithChildThrowsAndKeepsDept(): void
    {
        $deptId = $this->addDept('技术部');
        $this->addDept('后端组', $deptId);

        try {
            $this->deletion->delete($deptId);
            self::fail('部门有下级时应当抛 DeptNotEmptyException');
        } catch (DeptNotEmptyException) {
        }

        self::assertNotNull($this->depts->find($deptId));
    }

    public function testDeleteMissingDeptThrows(): void
    {
        $this->expectException(DeptNotFoundException::class);
        $this->deletion->delete(999999);
    }

    public function testDeleteTwiceThrowsOnSecondCall(): void
    {
        $deptId = $this->addDept('技术部');
        $this->deletion->delete($deptId);

        $this->expectException(DeptNotFoundException::class);
        $this->deletion->delete($deptId);
    }
}
