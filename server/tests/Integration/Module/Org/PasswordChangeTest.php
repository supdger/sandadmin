<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgException;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\UserService;

/** 自己改密码：旧口令不对拒绝，对了才换哈希。 */
final class PasswordChangeTest extends OrgCase
{
    public function testWrongOldPasswordStaysOnTheFormField(): void
    {
        $service = $this->service();
        $deptId = $this->addDept('改密部门');
        $userId = $this->addUser($deptId, '13800017061', '改密用户');
        $this->conn->update('sand_system_user', [
            'password_hash' => password_hash($this->secret('old'), PASSWORD_ARGON2ID),
            'username' => 'pw1705',
        ], ['id' => $userId]);

        try {
            $service->changePassword($userId, $this->secret('nope'), $this->secret('next'));
            self::fail('旧口令不对时必须拒绝');
        } catch (OrgException $error) {
            self::assertSame(422, $error->status);
            self::assertArrayHasKey('old_password', $error->fields);
        }
    }

    public function testCorrectOldPasswordReplacesTheHash(): void
    {
        $service = $this->service();
        $deptId = $this->addDept('改密部门');
        $userId = $this->addUser($deptId, '13800017062', '改密用户');
        $old = $this->secret('old');
        $next = $this->secret('next');
        $this->conn->update('sand_system_user', [
            'password_hash' => password_hash($old, PASSWORD_ARGON2ID),
            'username' => 'pw1705b',
        ], ['id' => $userId]);

        $service->changePassword($userId, $old, $next);
        $stored = $this->conn->fetchOne('SELECT password_hash FROM sand_system_user WHERE id = ?', [$userId]);
        self::assertIsString($stored);
        self::assertTrue(password_verify($next, $stored));
        self::assertFalse(password_verify($old, $stored));
    }

    private function service(): UserService
    {
        return new UserService($this->conn, new OrgAudit($this->conn), new OrgIdempotency(null));
    }

    /** @var array<string, string> */
    private array $secrets = [];

    /** 测试用口令随用例生成，不写死在源码里。 */
    private function secret(string $which): string
    {
        if (!array_key_exists($which, $this->secrets)) {
            $this->secrets[$which] = 'Aa1' . bin2hex(random_bytes(6));
        }

        return $this->secrets[$which];
    }
}
