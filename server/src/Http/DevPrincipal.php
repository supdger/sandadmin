<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Http;

use app\support\DevLoginGate;
use Doctrine\DBAL\Connection;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;
use support\Request;

/**
 * 开发本机登录之后的操作人。
 * 只在 SAND_DEV_LOGIN、回环地址、没有转发头、并且请求带了令牌时生效。
 * 令牌内容不解析：开发登录端点本身已经过同一道门，这里只确认「带了令牌」。
 */
final class DevPrincipal
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function resolve(object $request): ?OrgActor
    {
        if (!$request instanceof Request) {
            return null;
        }
        if (!DevLoginGate::allowed($request)) {
            return null;
        }
        $header = $request->header('authorization');
        if (!is_string($header) || !str_starts_with(strtolower($header), 'bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));
        if ($token === '') {
            return null;
        }
        $row = $this->ensure();
        if ($row === null) {
            return null;
        }

        return new OrgActor(
            OrgSql::int($row['id'] ?? null),
            OrgSql::str($row['nickname'] ?? '开发账号'),
            ($row['dept_id'] ?? null) === null ? null : OrgSql::int($row['dept_id']),
            true,
            ['*'],
        );
    }

    /** @return array<string, mixed>|null */
    private function ensure(): ?array
    {
        $found = $this->connection->fetchAssociative(
            'SELECT id, nickname, dept_id FROM sand_system_user WHERE lower(username) = \'admin\' AND delete_time IS NULL',
        );
        if ($found !== false) {
            return $found;
        }
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);
        $created = $this->connection->transactional(function () use ($hash): array {
            $deptId = OrgSql::int($this->connection->fetchOne(
                'INSERT INTO sand_system_dept (name, status) VALUES (\'开发部门\', 1)'
                . ' ON CONFLICT ON CONSTRAINT uk_sand_system_dept_parent_name DO NOTHING RETURNING id',
            ));
            if ($deptId <= 0) {
                $deptId = OrgSql::int($this->connection->fetchOne(
                    'SELECT id FROM sand_system_dept WHERE parent_id IS NULL AND name = \'开发部门\'',
                ));
            }
            $roleId = OrgSql::int($this->connection->fetchOne(
                'SELECT id FROM sand_system_role WHERE is_super LIMIT 1',
            ));
            if ($roleId <= 0) {
                $roleId = OrgSql::int($this->connection->fetchOne(
                    'INSERT INTO sand_system_role (name, code, is_system, is_super, data_scope)'
                    . ' VALUES (\'超级管理员\', \'super\', true, true, \'all\') RETURNING id',
                ));
            }
            $userId = OrgSql::int($this->connection->fetchOne(
                'INSERT INTO sand_system_user (username, phone, nickname, dept_id, password_hash, password_policy_tier,'
                . ' is_super, is_admin, status) VALUES (\'admin\', \'13900001111\', \'开发账号\', :dept, :hash, \'admin\', true, true, 1)'
                . ' RETURNING id',
                ['dept' => $deptId, 'hash' => $hash],
            ));
            $this->connection->insert('sand_system_user_role', ['user_id' => $userId, 'role_id' => $roleId]);
            $loaded = $this->connection->fetchAssociative(
                'SELECT id, nickname, dept_id FROM sand_system_user WHERE id = ?',
                [$userId],
            );

            return $loaded === false ? [] : $loaded;
        });

        return $created === [] ? null : $created;
    }
}
