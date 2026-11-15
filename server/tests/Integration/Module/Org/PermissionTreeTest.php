<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Tests\Integration\Module\Org;

use Sandadmin\Module\Org\OrgAudit;
use Sandadmin\Module\Org\OrgIdempotency;
use Sandadmin\Module\Org\RoleService;

/** 权限树必须收录服务端实际强校验的权限码，否则非超管角色永远授不上。 */
final class PermissionTreeTest extends OrgCase
{
    public function testTreeCoversEnforcedCodes(): void
    {
        $service = new RoleService($this->conn, new OrgAudit($this->conn), new OrgIdempotency(null));
        $codes = [];
        foreach ($service->permissionTree()['items'] as $group) {
            if (!is_array($group['menus'] ?? null)) {
                continue;
            }
            foreach ($group['menus'] as $menu) {
                if (!is_array($menu) || !is_array($menu['actions'] ?? null)) {
                    continue;
                }
                foreach ($menu['actions'] as $action) {
                    if (is_array($action) && is_string($action['code'] ?? null)) {
                        $codes[] = $action['code'];
                    }
                }
            }
        }

        // 监控和工具批次 + 同批核对发现的缺漏（task/plugin/update/file）
        foreach ([
            'system:monitor:index',
            'system:login-log:index', 'system:login-log:export',
            'system:audit-log:index', 'system:audit-log:export',
            'system:cron:index', 'system:cron:update', 'system:cron:run',
            'system:config:index', 'system:config:update',
            'system:task:index',
            'system:plugin:index', 'system:plugin:install', 'system:plugin:upgrade',
            'system:plugin:uninstall', 'system:plugin:upload',
            'system:update:index', 'system:update:check', 'system:update:start', 'system:update:upload',
            'system:file:index', 'system:file:delete', 'system:file:cleanup',
        ] as $code) {
            self::assertContains($code, $codes, '权限树缺 ' . $code);
        }
    }
}
