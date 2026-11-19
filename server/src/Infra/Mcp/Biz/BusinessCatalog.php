<?php

declare(strict_types=1);

// Copyright (c) 2026 青岛沙盒创意网络科技有限公司
// SPDX-License-Identifier: MIT

namespace Sandadmin\Infra\Mcp\Biz;

use Sandadmin\Domain\Mcp\BizGateway;
use Sandadmin\Domain\Mcp\ToolSpec;
use Sandadmin\Domain\Mcp\ToolTier;

/**
 * 业务 MCP 工具登记表（U-3：每个执行类工具对应一个现有 v1 接口与它的
 * 权限码）。显式清单，不走反射/注解发现；route/method/permission 三列
 * 与 contract/openapi.yaml 逐行对齐由契约测试机械核对——契约 x-mcp
 * 落地后本表改为构建期产物。档位口径（M-02）：read 只查；write 必带
 * 幂等键、支持 dry_run；high_risk 只生成待确认请求，永不直接执行。
 */
final class BusinessCatalog
{
    /** @return list<ToolSpec> */
    public static function specs(BizGateway $gateway): array
    {
        $specs = [];
        foreach (self::entries() as $entry) {
            $specs[] = new ToolSpec(
                $entry['name'],
                $entry['title'],
                $entry['description'],
                $entry['tier'],
                $entry['permission'],
                $entry['route'],
                $entry['input'],
                new BizToolRunner($gateway, $entry['operation']),
            );
        }

        return $specs;
    }

    /**
     * 登记原表，供契约测试逐行对照 openapi.yaml 与派发映射。
     *
     * @return list<array{name: string, title: string, description: string, tier: ToolTier, permission: string, method: string, route: string, operation: string, input: array<string, mixed>}>
     */
    public static function entries(): array
    {
        $listArgs = [
            'keyword' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['enabled', 'disabled']],
            'page' => ['type' => 'integer', 'minimum' => 1],
            'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
        ];
        $treeArgs = [
            'keyword' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['enabled', 'disabled']],
        ];

        return [
            self::entry('biz_menus_tree', '菜单树', '读取菜单树（对应 GET /api/admin/v1/menus/tree）', ToolTier::Read, 'system:menu:index', 'GET', '/api/admin/v1/menus/tree', 'menus_tree', ['keyword' => ['type' => 'string'], 'source' => ['type' => 'string']]),
            self::entry('biz_depts_tree', '部门树', '读取部门树（对应 GET /api/admin/v1/depts/tree）', ToolTier::Read, 'system:dept:index', 'GET', '/api/admin/v1/depts/tree', 'depts_tree', $treeArgs),
            self::entry('biz_roles_index', '角色列表', '分页查询角色（对应 GET /api/admin/v1/roles）', ToolTier::Read, 'system:role:index', 'GET', '/api/admin/v1/roles', 'roles_index', $listArgs),
            self::entry('biz_role_show', '角色详情', '读取单个角色（对应 GET /api/admin/v1/roles/{role_id}）', ToolTier::Read, 'system:role:index', 'GET', '/api/admin/v1/roles/{role_id}', 'roles_show', [], ['role_id']),
            self::entry('biz_role_permissions', '角色权限', '读取角色的权限码与数据范围（对应 GET /api/admin/v1/roles/{role_id}/permissions）', ToolTier::Read, 'system:role:index', 'GET', '/api/admin/v1/roles/{role_id}/permissions', 'roles_permissions', [], ['role_id']),
            self::entry('biz_permission_tree', '权限树', '读取权限点树（对应 GET /api/admin/v1/permission-tree）', ToolTier::Read, 'system:role:index', 'GET', '/api/admin/v1/permission-tree', 'permission_tree', []),
            self::entry('biz_users_index', '用户列表', '分页查询用户（对应 GET /api/admin/v1/users）', ToolTier::Read, 'system:user:index', 'GET', '/api/admin/v1/users', 'users_index', $listArgs + ['dept_id' => ['type' => 'string'], 'role_id' => ['type' => 'string']]),
            self::entry('biz_user_show', '用户详情', '读取单个用户（对应 GET /api/admin/v1/users/{user_id}）', ToolTier::Read, 'system:user:index', 'GET', '/api/admin/v1/users/{user_id}', 'users_show', [], ['user_id']),
            self::entry('biz_posts_index', '岗位列表', '分页查询岗位（对应 GET /api/admin/v1/posts）', ToolTier::Read, 'system:post:index', 'GET', '/api/admin/v1/posts', 'posts_index', $listArgs),
            self::entry('biz_dicts_index', '字典分组列表', '查询字典分组（对应 GET /api/admin/v1/dicts）', ToolTier::Read, 'system:dict:index', 'GET', '/api/admin/v1/dicts', 'dicts_index', ['keyword' => ['type' => 'string']]),
            self::entry('biz_dict_show', '字典分组详情', '读取字典分组与选项（对应 GET /api/admin/v1/dicts/{type_id}）', ToolTier::Read, 'system:dict:index', 'GET', '/api/admin/v1/dicts/{type_id}', 'dicts_show', [], ['type_id']),

            self::entry('biz_dept_create', '新建部门', '创建部门（对应 POST /api/admin/v1/depts），写操作带 idempotency_key', ToolTier::Write, 'system:dept:create', 'POST', '/api/admin/v1/depts', 'depts_create'),
            self::entry('biz_dept_update', '修改部门', '修改部门（对应 PUT /api/admin/v1/depts/{dept_id}）', ToolTier::Write, 'system:dept:update', 'PUT', '/api/admin/v1/depts/{dept_id}', 'depts_update', [], ['dept_id']),
            self::entry('biz_post_create', '新建岗位', '创建岗位（对应 POST /api/admin/v1/posts），写操作带 idempotency_key', ToolTier::Write, 'system:post:create', 'POST', '/api/admin/v1/posts', 'posts_create'),
            self::entry('biz_post_update', '修改岗位', '修改岗位（对应 PUT /api/admin/v1/posts/{post_id}）', ToolTier::Write, 'system:post:update', 'PUT', '/api/admin/v1/posts/{post_id}', 'posts_update', [], ['post_id']),
            self::entry('biz_dict_create', '新建字典分组', '创建字典分组（对应 POST /api/admin/v1/dicts），写操作带 idempotency_key', ToolTier::Write, 'system:dict:create', 'POST', '/api/admin/v1/dicts', 'dicts_create'),
            self::entry('biz_dict_update', '修改字典分组', '修改字典分组（对应 PUT /api/admin/v1/dicts/{type_id}）', ToolTier::Write, 'system:dict:update', 'PUT', '/api/admin/v1/dicts/{type_id}', 'dicts_update', [], ['type_id']),
            self::entry('biz_menu_create_group', '新建菜单分组', '创建菜单分组（对应 POST /api/admin/v1/menus/groups），写操作带 idempotency_key', ToolTier::Write, 'system:menu:create', 'POST', '/api/admin/v1/menus/groups', 'menus_create_group'),
            self::entry('biz_menu_create_link', '新建菜单链接', '创建外部链接菜单（对应 POST /api/admin/v1/menus/links），写操作带 idempotency_key', ToolTier::Write, 'system:menu:create', 'POST', '/api/admin/v1/menus/links', 'menus_create_link'),
            self::entry('biz_menu_update', '修改菜单', '修改菜单节点（对应 PATCH /api/admin/v1/menus/{menu_key}）', ToolTier::Write, 'system:menu:update', 'PATCH', '/api/admin/v1/menus/{menu_key}', 'menus_update', [], ['menu_key']),
            self::entry('biz_menu_move', '移动菜单', '调整菜单位置或父级（对应 POST /api/admin/v1/menus/{menu_key}/move）', ToolTier::Write, 'system:menu:update', 'POST', '/api/admin/v1/menus/{menu_key}/move', 'menus_move', [], ['menu_key']),
            self::entry('biz_menu_visible', '菜单显隐', '切换菜单可见性（对应 PUT /api/admin/v1/menus/{menu_key}/visible）', ToolTier::Write, 'system:menu:update', 'PUT', '/api/admin/v1/menus/{menu_key}/visible', 'menus_visible', [], ['menu_key']),
            self::entry('biz_menus_reset', '重置菜单树', '恢复默认菜单树（对应 POST /api/admin/v1/menus/reset），写操作带 idempotency_key', ToolTier::Write, 'system:menu:reset', 'POST', '/api/admin/v1/menus/reset', 'menus_reset'),

            self::entry('biz_users_export', '导出全部用户', '导出全量用户（对应 POST /api/admin/v1/users/export）——N4 高危，只生成待确认请求', ToolTier::HighRisk, 'system:user:export', 'POST', '/api/admin/v1/users/export', 'users_export'),
            self::entry('biz_user_reset_password', '重置用户密码', '重置他人密码（对应 POST /api/admin/v1/users/{user_id}/reset-password）——N4 高危，只生成待确认请求', ToolTier::HighRisk, 'system:user:reset-password', 'POST', '/api/admin/v1/users/{user_id}/reset-password', 'users_reset_password', [], ['user_id']),
            self::entry('biz_user_assign_roles', '分配用户角色', '调整用户角色（对应 PUT /api/admin/v1/users/{user_id}/roles）——N4 高危，只生成待确认请求', ToolTier::HighRisk, 'system:user:assign-role', 'PUT', '/api/admin/v1/users/{user_id}/roles', 'users_assign_roles', [], ['user_id']),
            self::entry('biz_role_save_permissions', '保存角色权限', '修改角色权限码与数据范围（对应 PUT /api/admin/v1/roles/{role_id}/permissions）——N4 高危，只生成待确认请求', ToolTier::HighRisk, 'system:role:permission:update', 'PUT', '/api/admin/v1/roles/{role_id}/permissions', 'roles_save_permissions', [], ['role_id']),
        ];
    }

    /**
     * @param array<string, mixed> $properties 已声明的入参字段；写工具的其余字段
     *        按 additionalProperties:true 透传给 v1 同一套入参校验
     * @param list<string>          $required
     *
     * @return array{name: string, title: string, description: string, tier: ToolTier, permission: string, method: string, route: string, operation: string, input: array<string, mixed>}
     */
    private static function entry(
        string $name,
        string $title,
        string $description,
        ToolTier $tier,
        string $permission,
        string $method,
        string $route,
        string $operation,
        array $properties = [],
        array $required = [],
    ): array {
        if ($tier !== ToolTier::Read) {
            $properties += [
                'idempotency_key' => ['type' => 'string', 'description' => '写档必填：同键重复提交返回首次结果'],
                'dry_run' => ['type' => 'boolean', 'description' => '只校验不执行'],
            ];
        }
        $input = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => $tier !== ToolTier::Read,
        ];
        if ($required !== []) {
            $input['required'] = $required;
        }

        return [
            'name' => $name,
            'title' => $title,
            'description' => $description,
            'tier' => $tier,
            'permission' => $permission,
            'method' => $method,
            'route' => $route,
            'operation' => $operation,
            'input' => $input,
        ];
    }
}
