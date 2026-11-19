<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Biz;

use Doctrine\DBAL\Connection;
use Sandadmin\Domain\Mcp\McpActorSource;
use Sandadmin\Domain\Mcp\McpIdentity;
use Sandadmin\Module\Org\OrgActor;
use Sandadmin\Module\Org\OrgSql;

/**
 * 按 username 把 MCP 身份落成 sand_system_user 行对应的 OrgActor。
 * 权限口径与 DevPrincipal 一致：账号 is_super 给全量，否则取角色权限码并集；
 * 再按凭据 permissions 收窄（U-3/M-01：scope 只能缩小不能放大）——
 * 收窄后 is_super 必须落 false，否则 OrgActor::can 会跳过码清单。
 * 与 DevPrincipal 同一判定面：只看 delete_time，账号停用的联动失效
 * 随机器令牌（authz_version）接通时补齐。
 */
final class OrgActorSource implements McpActorSource
{
    public function resolve(McpIdentity $identity, Connection $connection): ?OrgActor
    {
        $row = $connection->fetchAssociative(
            'SELECT id, nickname, dept_id, is_super FROM sand_system_user'
            . ' WHERE lower(username) = lower(:name) AND delete_time IS NULL',
            ['name' => $identity->subject],
        );
        if ($row === false) {
            return null;
        }
        $userId = OrgSql::int($row['id'] ?? null);
        $isSuper = self::truthy($row['is_super'] ?? null);
        $dept = ($row['dept_id'] ?? null) === null ? null : OrgSql::int($row['dept_id']);
        $codes = $isSuper ? ['*'] : $this->permissionCodes($connection, $userId);

        if (in_array('*', $identity->permissions, true)) {
            return new OrgActor($userId, OrgSql::str($row['nickname'] ?? $identity->subject), $dept, $isSuper, $codes);
        }
        $scoped = $isSuper
            ? $identity->permissions
            : array_values(array_intersect($codes, $identity->permissions));

        return new OrgActor($userId, OrgSql::str($row['nickname'] ?? $identity->subject), $dept, false, $scoped);
    }

    /** @return list<string> */
    private function permissionCodes(Connection $connection, int $userId): array
    {
        $rows = $connection->fetchFirstColumn(
            'SELECT DISTINCT rp.permission_code FROM sand_system_role_permission rp'
            . ' JOIN sand_system_user_role ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?',
            [$userId],
        );
        $codes = [];
        foreach ($rows as $code) {
            if (is_string($code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 't' || $value === 1 || $value === '1';
    }
}
