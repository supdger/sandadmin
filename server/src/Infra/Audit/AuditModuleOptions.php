<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Audit;

use Doctrine\DBAL\Connection;
use Sandadmin\Domain\Audit\AuditModuleOption;

/**
 * GET /audit-logs/module-options 的功能选项汇总：宿主核心功能固定清单 +
 * sand_system_plugin 里 state='installed' 的插件，对应 /audit-logs 的
 * module 筛选参数。宿主模块键取自 openapi 中 x-audit:true 写操作的归属
 * 资源（与 audit_log.module 写者口径一致）；name 为中文名，插件取
 * sand_system_plugin.name。输出去重：宿主在前固定顺序，插件按 code 升序；
 * 插件 code 与宿主键撞名时宿主优先。
 */
final readonly class AuditModuleOptions
{
    private const PLUGIN_TABLE = 'sand_system_plugin';

    /** 宿主模块键 → 中文名；顺序即输出顺序。 */
    private const HOST_MODULES = [
        'user' => '用户管理',
        'dept' => '部门管理',
        'post' => '岗位管理',
        'role' => '角色权限',
        'menu' => '菜单管理',
        'dict' => '数据字典',
        'file' => '附件管理',
        'announcement' => '系统公告',
        'setting' => '系统设置',
        'schedule' => '定时任务',
        'login_log' => '登录日志',
        'audit_log' => '操作日志',
        'plugin' => '插件管理',
        'update' => '系统更新',
        'setup' => '首次设置',
        'auth' => '认证会话',
        'me' => '个人中心',
        'dashboard' => '工作台',
        'client' => '接入应用',
        'sand_ai' => 'AI 服务商',
    ];

    public function __construct(private Connection $connection)
    {
    }

    /** @return list<AuditModuleOption> */
    public function options(): array
    {
        $out = [];
        $seen = [];
        foreach (self::HOST_MODULES as $key => $name) {
            $out[] = new AuditModuleOption($key, $name);
            $seen[$key] = true;
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('code', 'name')
            ->from(self::PLUGIN_TABLE)
            ->where("state = 'installed'")
            ->orderBy('code', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
        foreach ($rows as $row) {
            $code = $row['code'] ?? null;
            $name = $row['name'] ?? null;
            if (!is_string($code) || isset($seen[$code])) {
                continue;
            }
            // name 列 NOT NULL；防御性落到 code，保证选项可用
            $out[] = new AuditModuleOption($code, is_string($name) && $name !== '' ? $name : $code);
            $seen[$code] = true;
        }

        return $out;
    }
}
