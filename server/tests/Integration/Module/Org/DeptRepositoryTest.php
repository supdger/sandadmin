<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

use Sandadmin\Module\Org\DeptLeader;

/** 部门仓储：负责人展示视图与清负责人。需要 SAND_TEST_DB_URL（见 OrgCase）。 */
final class DeptRepositoryTest extends OrgCase
{
    /** @return array<int, DeptLeader> */
    private function leaderViewsByDept(): array
    {
        $views = [];
        foreach ($this->depts->listLeaderViews() as $view) {
            $views[$view->deptId] = $view;
        }

        return $views;
    }

    public function testLeaderViewUnsetDept(): void
    {
        $deptId = $this->addDept('技术部');
        $view = $this->leaderViewsByDept()[$deptId];

        self::assertNull($view->leaderId);
        self::assertNull($view->leaderName);
        self::assertNull($view->avatarFileId);
        self::assertNull($view->leaderStatus);
    }

    public function testLeaderViewEnabledAndDisabled(): void
    {
        $enabledDeptId = $this->addDept('技术部');
        $disabledDeptId = $this->addDept('市场部');
        $enabledUser = $this->addUser($enabledDeptId, '13800000020', '张三');
        $disabledUser = $this->addUser($disabledDeptId, '13800000021', '李四', 2);
        $this->conn->executeStatement('UPDATE sand_system_dept SET leader_id = ? WHERE id = ?', [$enabledUser, $enabledDeptId]);
        $this->conn->executeStatement('UPDATE sand_system_dept SET leader_id = ? WHERE id = ?', [$disabledUser, $disabledDeptId]);

        $views = $this->leaderViewsByDept();

        self::assertSame($enabledUser, $views[$enabledDeptId]->leaderId);
        self::assertSame('张三', $views[$enabledDeptId]->leaderName);
        self::assertSame('enabled', $views[$enabledDeptId]->leaderStatus);

        self::assertSame($disabledUser, $views[$disabledDeptId]->leaderId);
        self::assertSame('李四', $views[$disabledDeptId]->leaderName);
        self::assertSame('disabled', $views[$disabledDeptId]->leaderStatus);
    }

    public function testLeaderViewDeletedLeaderShowsUnset(): void
    {
        $deptId = $this->addDept('技术部');
        $userId = $this->addUser($deptId, '13800000022', '王五');
        $this->conn->executeStatement('UPDATE sand_system_dept SET leader_id = ? WHERE id = ?', [$userId, $deptId]);
        $this->conn->executeStatement('UPDATE sand_system_user SET delete_time = CURRENT_TIMESTAMP, dept_id = NULL WHERE id = ?', [$userId]);

        $view = $this->leaderViewsByDept()[$deptId];

        self::assertNull($view->leaderId);
        self::assertNull($view->leaderName);
        self::assertNull($view->leaderStatus);
    }

    public function testClearLeader(): void
    {
        $deptId = $this->addDept('技术部');
        $userId = $this->addUser($deptId, '13800000023');
        $this->conn->executeStatement('UPDATE sand_system_dept SET leader_id = ? WHERE id = ?', [$userId, $deptId]);

        self::assertSame(1, $this->depts->clearLeader($userId));
        $row = $this->fetchRow('sand_system_dept', $deptId);
        self::assertNotNull($row);
        self::assertNull($row['leader_id']);

        self::assertSame(0, $this->depts->clearLeader($userId));
    }

    public function testCountChildren(): void
    {
        $deptId = $this->addDept('技术部');
        self::assertSame(0, $this->depts->countChildren($deptId));
        $this->addDept('后端组', $deptId);
        $this->addDept('前端组', $deptId);
        $this->addDept('市场部');
        self::assertSame(2, $this->depts->countChildren($deptId));
    }
}
