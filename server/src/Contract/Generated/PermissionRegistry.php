<?php

declare(strict_types=1);

// <contract-generated> 本文件由 tools/contract-build 生成，请勿手改；
// 改了会在契约一致性门禁（tools/contract-build --check）被打回。
// 源: contract/openapi.yaml
// 源哈希: sha256:fb32ff5719edd1a74d399bd0a765fc240e7780d7a89eda2379fd3c17f57d75fb
// 生成器: contract-build v1

namespace Sandadmin\Contract\Generated;

/**
 * 权限注册表：契约派生的权限码常量与展示树。
 * 判定面一律引用这里的常量，不再散落字符串字面量。
 */
final class PermissionRegistry
{
    public const MCP_DEV_ERRORS = 'mcp:dev:errors';
    public const MCP_DEV_GATE = 'mcp:dev:gate';
    public const MCP_DEV_PHPUNIT = 'mcp:dev:phpunit';
    public const MCP_DEV_ROUTES = 'mcp:dev:routes';
    public const ANNOUNCEMENT_CREATE = 'system:announcement:create';
    public const ANNOUNCEMENT_DELETE = 'system:announcement:delete';
    public const ANNOUNCEMENT_INDEX = 'system:announcement:index';
    public const ANNOUNCEMENT_PUBLISH = 'system:announcement:publish';
    public const ANNOUNCEMENT_REVOKE = 'system:announcement:revoke';
    public const ANNOUNCEMENT_UPDATE = 'system:announcement:update';
    public const DEPT_CREATE = 'system:dept:create';
    public const DEPT_DELETE = 'system:dept:delete';
    public const DEPT_INDEX = 'system:dept:index';
    public const DEPT_UPDATE = 'system:dept:update';
    public const DICT_CREATE = 'system:dict:create';
    public const DICT_DELETE = 'system:dict:delete';
    public const DICT_INDEX = 'system:dict:index';
    public const DICT_UPDATE = 'system:dict:update';
    public const FILE_CLEANUP = 'system:file:cleanup';
    public const FILE_DELETE = 'system:file:delete';
    public const FILE_INDEX = 'system:file:index';
    public const MENU_CREATE = 'system:menu:create';
    public const MENU_DELETE = 'system:menu:delete';
    public const MENU_INDEX = 'system:menu:index';
    public const MENU_RESET = 'system:menu:reset';
    public const MENU_UPDATE = 'system:menu:update';
    public const PLUGIN_INDEX = 'system:plugin:index';
    public const POST_CREATE = 'system:post:create';
    public const POST_DELETE = 'system:post:delete';
    public const POST_INDEX = 'system:post:index';
    public const POST_UPDATE = 'system:post:update';
    public const ROLE_CREATE = 'system:role:create';
    public const ROLE_DELETE = 'system:role:delete';
    public const ROLE_INDEX = 'system:role:index';
    public const ROLE_PERMISSION_UPDATE = 'system:role:permission:update';
    public const ROLE_UPDATE = 'system:role:update';
    public const TASK_INDEX = 'system:task:index';
    public const UPDATE_INDEX = 'system:update:index';
    public const USER_ASSIGN_ROLE = 'system:user:assign-role';
    public const USER_CREATE = 'system:user:create';
    public const USER_DELETE = 'system:user:delete';
    public const USER_EXPORT = 'system:user:export';
    public const USER_INDEX = 'system:user:index';
    public const USER_RESET_PASSWORD = 'system:user:reset-password';
    public const USER_UPDATE = 'system:user:update';

    public const SIDEBAR_USER = 'system:user';
    public const SIDEBAR_DEPT = 'system:dept';
    public const SIDEBAR_POST = 'system:post';
    public const SIDEBAR_ROLE = 'system:role';
    public const SIDEBAR_DICT = 'system:dict';
    public const SIDEBAR_ANNOUNCEMENT = 'system:announcement';
    public const SIDEBAR_MENU = 'system:menu';
    public const SIDEBAR_PLUGIN = 'system:plugin';
    public const SIDEBAR_UPDATE = 'system:update';

    /** @var list<string> 契约声明的全部权限码 */
    public const CODES = ['mcp:dev:errors', 'mcp:dev:gate', 'mcp:dev:phpunit', 'mcp:dev:routes', 'system:announcement:create', 'system:announcement:delete', 'system:announcement:index', 'system:announcement:publish', 'system:announcement:revoke', 'system:announcement:update', 'system:dept:create', 'system:dept:delete', 'system:dept:index', 'system:dept:update', 'system:dict:create', 'system:dict:delete', 'system:dict:index', 'system:dict:update', 'system:file:cleanup', 'system:file:delete', 'system:file:index', 'system:menu:create', 'system:menu:delete', 'system:menu:index', 'system:menu:reset', 'system:menu:update', 'system:plugin:index', 'system:post:create', 'system:post:delete', 'system:post:index', 'system:post:update', 'system:role:create', 'system:role:delete', 'system:role:index', 'system:role:permission:update', 'system:role:update', 'system:task:index', 'system:update:index', 'system:user:assign-role', 'system:user:create', 'system:user:delete', 'system:user:export', 'system:user:index', 'system:user:reset-password', 'system:user:update'];

    /** @var list<string> 管理员高危码（顶掉 UserService::ADMIN_CODES） */
    public const ADMIN_CODES = ['system:user:create', 'system:user:update', 'system:user:reset-password', 'system:user:assign-role', 'system:role:permission:update'];

    /** @var list<string> 侧栏菜单键（顶掉 MenuService 手写键） */
    public const MENU_KEYS = ['system:user', 'system:dept', 'system:post', 'system:role', 'system:dict', 'system:announcement', 'system:menu', 'system:plugin', 'system:update'];

    /**
     * @var list<array{group_key: string, group_title: string, menu_key: string, title: string, actions: list<array{action: string, label: string}>}>
     *      角色配置权限树（顶掉 RoleService::MENUS）
     */
    public const TREE = [
        [
            'group_key' => 'org',
            'group_title' => '组织与用户',
            'menu_key' => 'system:user',
            'title' => '用户管理',
            'actions' => [
                [
                    'action' => 'index',
                    'label' => '查看',
                ],
                [
                    'action' => 'create',
                    'label' => '添加',
                ],
                [
                    'action' => 'update',
                    'label' => '编辑',
                ],
                [
                    'action' => 'delete',
                    'label' => '删除',
                ],
                [
                    'action' => 'export',
                    'label' => '导出',
                ],
                [
                    'action' => 'reset-password',
                    'label' => '重置密码',
                ],
                [
                    'action' => 'assign-role',
                    'label' => '分配角色',
                ],
            ],
        ],
        [
            'group_key' => 'org',
            'group_title' => '组织与用户',
            'menu_key' => 'system:dept',
            'title' => '部门管理',
            'actions' => [
                [
                    'action' => 'index',
                    'label' => '查看',
                ],
                [
                    'action' => 'create',
                    'label' => '添加',
                ],
                [
                    'action' => 'update',
                    'label' => '编辑',
                ],
                [
                    'action' => 'delete',
                    'label' => '删除',
                ],
            ],
        ],
        [
            'group_key' => 'org',
            'group_title' => '组织与用户',
            'menu_key' => 'system:post',
            'title' => '岗位管理',
            'actions' => [
                [
                    'action' => 'index',
                    'label' => '查看',
                ],
                [
                    'action' => 'create',
                    'label' => '添加',
                ],
                [
                    'action' => 'update',
                    'label' => '编辑',
                ],
                [
                    'action' => 'delete',
                    'label' => '删除',
                ],
            ],
        ],
        [
            'group_key' => 'org',
            'group_title' => '组织与用户',
            'menu_key' => 'system:role',
            'title' => '角色与权限',
            'actions' => [
                [
                    'action' => 'index',
                    'label' => '查看',
                ],
                [
                    'action' => 'create',
                    'label' => '添加',
                ],
                [
                    'action' => 'update',
                    'label' => '编辑',
                ],
                [
                    'action' => 'delete',
                    'label' => '删除',
                ],
                [
                    'action' => 'permission:update',
                    'label' => '设置权限',
                ],
            ],
        ],
        [
            'group_key' => 'system',
            'group_title' => '系统',
            'menu_key' => 'system:menu',
            'title' => '菜单管理',
            'actions' => [
                [
                    'action' => 'index',
                    'label' => '查看',
                ],
                [
                    'action' => 'create',
                    'label' => '添加',
                ],
                [
                    'action' => 'update',
                    'label' => '编辑',
                ],
                [
                    'action' => 'delete',
                    'label' => '删除',
                ],
                [
                    'action' => 'reset',
                    'label' => '恢复默认',
                ],
            ],
        ],
        [
            'group_key' => 'data',
            'group_title' => '数据管理',
            'menu_key' => 'system:dict',
            'title' => '选项管理',
            'actions' => [
                [
                    'action' => 'index',
                    'label' => '查看',
                ],
                [
                    'action' => 'create',
                    'label' => '添加',
                ],
                [
                    'action' => 'update',
                    'label' => '编辑',
                ],
                [
                    'action' => 'delete',
                    'label' => '删除',
                ],
            ],
        ],
        [
            'group_key' => 'data',
            'group_title' => '数据管理',
            'menu_key' => 'system:announcement',
            'title' => '公告管理',
            'actions' => [
                [
                    'action' => 'index',
                    'label' => '查看',
                ],
                [
                    'action' => 'create',
                    'label' => '添加',
                ],
                [
                    'action' => 'update',
                    'label' => '编辑',
                ],
                [
                    'action' => 'delete',
                    'label' => '删除',
                ],
                [
                    'action' => 'publish',
                    'label' => '发布',
                ],
                [
                    'action' => 'revoke',
                    'label' => '收回',
                ],
            ],
        ],
    ];

    /** @var list<array{key: string, title: string, icon: string, path: string}> 侧栏菜单定义 */
    public const SIDEBAR = [
        [
            'key' => 'system:user',
            'title' => '用户管理',
            'icon' => 'user',
            'path' => '/system/user',
        ],
        [
            'key' => 'system:dept',
            'title' => '部门管理',
            'icon' => 'dept',
            'path' => '/system/dept',
        ],
        [
            'key' => 'system:post',
            'title' => '岗位管理',
            'icon' => 'post',
            'path' => '/system/post',
        ],
        [
            'key' => 'system:role',
            'title' => '角色管理',
            'icon' => 'role',
            'path' => '/system/role',
        ],
        [
            'key' => 'system:dict',
            'title' => '选项管理',
            'icon' => 'dict',
            'path' => '/data/dict',
        ],
        [
            'key' => 'system:announcement',
            'title' => '公告管理',
            'icon' => 'notice',
            'path' => '/data/notice',
        ],
        [
            'key' => 'system:menu',
            'title' => '菜单管理',
            'icon' => 'menu',
            'path' => '/system/menu',
        ],
        [
            'key' => 'system:plugin',
            'title' => '插件管理',
            'icon' => 'plugin',
            'path' => '/system/plugins',
        ],
        [
            'key' => 'system:update',
            'title' => '系统更新',
            'icon' => 'refresh',
            'path' => '/system/update',
        ],
    ];

    /** @param list<string> $codes */
    public static function allDeclared(array $codes): bool
    {
        foreach ($codes as $code) {
            if (!in_array($code, self::CODES, true)) {
                return false;
            }
        }

        return true;
    }
}
