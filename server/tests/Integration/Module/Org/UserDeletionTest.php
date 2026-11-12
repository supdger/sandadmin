<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

use Sandadmin\Module\Org\UserDeletion;

/** 删除用户联动（J6）。需要 SAND_TEST_DB_URL（见 OrgCase）。 */
final class UserDeletionTest extends OrgCase
{
    private UserDeletion $deletion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deletion = new UserDeletion($this->conn, $this->users, $this->depts);
    }

    public function testDeleteMarksUserAndClearsLeader(): void
    {
        $deptId = $this->addDept('技术部');
        $userId = $this->addUser($deptId, '13800000030');
        $this->conn->executeStatement('UPDATE sand_system_dept SET leader_id = ? WHERE id = ?', [$userId, $deptId]);

        self::assertTrue($this->deletion->delete($userId));

        $user = $this->users->find($userId);
        self::assertNotNull($user);
        self::assertNotNull($user->deleteTime);
        self::assertNull($user->deptId);

        $deptRow = $this->fetchRow('sand_system_dept', $deptId);
        self::assertNotNull($deptRow);
        self::assertNull($deptRow['leader_id']);
    }

    public function testDeleteIsIdempotent(): void
    {
        $deptId = $this->addDept('技术部');
        $userId = $this->addUser($deptId, '13800000031');

        self::assertTrue($this->deletion->delete($userId));
        self::assertFalse($this->deletion->delete($userId));
    }

    public function testDeleteMissingUserReturnsFalse(): void
    {
        self::assertFalse($this->deletion->delete(999999));
    }

    /** 软删后手机号与登录账号自动释放（部分唯一索引，无需置空字段）。 */
    public function testDeletedUserReleasesPhoneAndUsername(): void
    {
        $deptId = $this->addDept('技术部');
        $userId = $this->addUser($deptId, '13800000032');
        $this->conn->executeStatement(
            'UPDATE sand_system_user SET username = ? WHERE id = ?',
            ['zhaosan', $userId],
        );

        self::assertTrue($this->deletion->delete($userId));

        $this->conn->insert('sand_system_user', [
            'phone' => '13800000032',
            'username' => 'zhaosan',
            'nickname' => '新用户',
            'password_hash' => 'argon2id$test',
            'dept_id' => $deptId,
        ]);
        self::assertNotFalse($this->conn->lastInsertId());
    }

    /** clearLeader 失败时整个事务回滚：软删不残留半成品。 */
    public function testDeleteRollsBackWhenClearLeaderFails(): void
    {
        $deptId = $this->addDept('技术部');
        $userId = $this->addUser($deptId, '13800000033');
        $this->conn->executeStatement('UPDATE sand_system_dept SET leader_id = ? WHERE id = ?', [$userId, $deptId]);

        // 触发器拦下「leader_id 置空」的 UPDATE，让 clearLeader 在事务内失败
        $this->conn->executeStatement(<<<'SQL'
            CREATE FUNCTION org_test_fail_clear() RETURNS trigger AS $body$
            BEGIN
                RAISE EXCEPTION 'org_test_fail_clear';
            END;
            $body$ LANGUAGE plpgsql
        SQL);
        $this->conn->executeStatement(
            'CREATE TRIGGER org_test_fail_clear BEFORE UPDATE ON sand_system_dept'
            . ' FOR EACH ROW WHEN (NEW.leader_id IS NULL) EXECUTE FUNCTION org_test_fail_clear()'
        );

        try {
            $this->deletion->delete($userId);
            self::fail('clearLeader 被触发器拦下时应当抛异常');
        } catch (\Throwable) {
        } finally {
            $this->conn->executeStatement('DROP TRIGGER IF EXISTS org_test_fail_clear ON sand_system_dept');
            $this->conn->executeStatement('DROP FUNCTION IF EXISTS org_test_fail_clear()');
        }

        $user = $this->users->find($userId);
        self::assertNotNull($user);
        self::assertNull($user->deleteTime);
        self::assertSame($deptId, $user->deptId);

        $deptRow = $this->fetchRow('sand_system_dept', $deptId);
        self::assertNotNull($deptRow);
        $leaderId = $deptRow['leader_id'];
        self::assertTrue(is_numeric($leaderId));
        self::assertSame($userId, (int) $leaderId);
    }
}
