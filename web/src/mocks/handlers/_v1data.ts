import type { Dept } from '@/api/v1/depts';
import type { Post } from '@/api/v1/posts';
import type { Role } from '@/api/v1/roles';
import type { Task } from '@/api/v1/tasks';
import type { User } from '@/api/v1/users';
import type { MenuTree } from '@/api/v1/menus';
import type { PermissionGroup } from '@/api/v1/permissions';

/**
 * `/api/admin/v1` mock 的可变内存状态。写入在页面生命周期内持久
 * （模块级状态）；测试通过 `resetV1Mock` 重置。
 */

// 涉及权限的数据每次变化都递增，让客户端的
// X-Authz-Version 监听能失效 `['me']`/菜单查询。
let authzVersionCounter = 1;
export function authzVersion(): number {
  return authzVersionCounter;
}
export function bumpAuthzVersion(): void {
  authzVersionCounter += 1;
}

const T0 = '2026-01-05T09:30:00+08:00';

export const mockDepts: Dept[] = [
  {
    id: 'dept-root',
    parent_id: null,
    name: '沙盒网络',
    leader_id: 'u-admin',
    leader_name: '管理员',
    leader_avatar: null,
    leader_status: 'enabled',
    phone: '0532-88880000',
    sort: 0,
    status: 'enabled',
    user_count: 26,
    update_time: T0,
    children: [
      {
        id: 'dept-tech',
        parent_id: 'dept-root',
        name: '技术部',
        leader_id: 'u-admin',
        leader_name: '管理员',
        leader_avatar: null,
        leader_status: 'enabled',
        phone: '0532-88880001',
        sort: 1,
        status: 'enabled',
        user_count: 11,
        update_time: T0,
        children: [
          {
            id: 'dept-backend',
            parent_id: 'dept-tech',
            name: '后端组',
            leader_id: 'u-zhangsan',
            leader_name: '张三',
            leader_avatar: null,
            leader_status: 'enabled',
            phone: null,
            sort: 1,
            status: 'enabled',
            user_count: 6,
            update_time: T0,
            children: [],
          },
          {
            id: 'dept-frontend',
            parent_id: 'dept-tech',
            name: '前端组',
            leader_id: 'u-lisi',
            leader_name: '李四',
            leader_avatar: null,
            leader_status: 'enabled',
            phone: null,
            sort: 2,
            status: 'enabled',
            user_count: 5,
            update_time: T0,
            children: [],
          },
        ],
      },
      {
        id: 'dept-product',
        parent_id: 'dept-root',
        name: '产品部',
        leader_id: 'u-wangwu',
        leader_name: '王五',
        leader_avatar: null,
        leader_status: 'enabled',
        phone: '0532-88880002',
        sort: 2,
        status: 'enabled',
        user_count: 8,
        update_time: T0,
        children: [],
      },
      {
        id: 'dept-ops',
        parent_id: 'dept-root',
        name: '运营部',
        leader_id: null,
        leader_name: null,
        leader_avatar: null,
        leader_status: null,
        phone: null,
        sort: 3,
        status: 'disabled',
        user_count: 1,
        update_time: T0,
        children: [],
      },
      {
        id: 'dept-service',
        parent_id: 'dept-root',
        name: '客服部',
        leader_id: 'u-012',
        leader_name: '李芳',
        leader_avatar: null,
        leader_status: 'disabled',
        phone: null,
        sort: 4,
        status: 'enabled',
        user_count: 2,
        update_time: T0,
        children: [],
      },
    ],
  },
];

export const mockPosts: Post[] = [
  { id: 'post-1', name: '高级后端工程师', remark: '负责核心服务', sort: 1, status: 'enabled', user_count: 4, create_time: T0 },
  { id: 'post-2', name: '前端工程师', remark: null, sort: 2, status: 'enabled', user_count: 5, create_time: T0 },
  { id: 'post-3', name: '产品经理', remark: '需求与原型', sort: 3, status: 'enabled', user_count: 2, create_time: T0 },
  { id: 'post-4', name: '运营专员', remark: null, sort: 4, status: 'enabled', user_count: 3, create_time: T0 },
  { id: 'post-5', name: '测试工程师', remark: null, sort: 5, status: 'enabled', user_count: 2, create_time: T0 },
  { id: 'post-6', name: '实习生', remark: '校招通道', sort: 6, status: 'disabled', user_count: 0, create_time: T0 },
];

export const mockRoles: Role[] = [
  { id: 'role-super', name: '超级管理员', remark: '内置角色，不可修改', is_system: true, is_super: true, status: 'enabled', data_scope: 'all', user_count: 1, update_time: T0 },
  { id: 'role-admin', name: '系统管理员', remark: '日常运维权限', is_system: true, is_super: false, status: 'enabled', data_scope: 'all', user_count: 2, update_time: T0 },
  { id: 'role-tech-lead', name: '技术负责人', remark: null, is_system: false, is_super: false, status: 'enabled', data_scope: 'dept_and_child', user_count: 3, update_time: T0 },
  { id: 'role-ops', name: '运营专员', remark: null, is_system: false, is_super: false, status: 'enabled', data_scope: 'dept', user_count: 4, update_time: T0 },
  { id: 'role-viewer', name: '只读访客', remark: '仅查看', is_system: false, is_super: false, status: 'disabled', data_scope: 'self', user_count: 0, update_time: T0 },
  { id: 'role-brand', name: '品牌推广', remark: '只看指定部门的数据', is_system: false, is_super: false, status: 'enabled', data_scope: 'custom', user_count: 1, update_time: T0 },
];

function makeUser(i: number, overrides: Partial<User> = {}): User {
  const deptCycle = ['dept-tech', 'dept-product', 'dept-ops', 'dept-root'];
  const surnames = ['张', '李', '王', '赵', '陈', '刘', '杨', '黄', '周', '吴'];
  const given = ['伟', '芳', '娜', '敏', '静', '磊', '洋', '艳', '勇', '军'];
  const deptId = deptCycle[i % deptCycle.length];
  const disabled = i % 11 === 0;
  const postId =
    overrides.post_id !== undefined
      ? overrides.post_id
      : i % 3 === 0
        ? 'post-1'
        : i % 3 === 1
          ? 'post-2'
          : 'post-3';
  const roleIds =
    overrides.role_ids ?? (i % 4 === 0 ? ['role-admin'] : i % 4 === 1 ? ['role-tech-lead'] : ['role-ops']);
  return {
    id: `u-${String(i + 1).padStart(3, '0')}`,
    username: `user${String(i + 1).padStart(3, '0')}`,
    nickname: `${surnames[i % surnames.length]}${given[i % given.length]}`,
    avatar: null,
    avatar_file_id: null,
    phone: `138${String(10000000 + i * 137).slice(0, 8)}`,
    email: `user${i + 1}@example.com`,
    status: disabled ? 'disabled' : 'enabled',
    is_admin: i === 0,
    is_super: i === 0,
    must_change_password: i === 1,
    permissions: i === 0 ? ['*'] : [],
    dept_id: deptId,
    dept_name: { 'dept-tech': '技术部', 'dept-product': '产品部', 'dept-ops': '运营部', 'dept-root': '沙盒网络' }[deptId] ?? null,
    post_id: postId,
    post_name: mockPosts.find((post) => post.id === postId)?.name ?? null,
    role_ids: roleIds,
    role_names: roleIds.map((roleId) => mockRoles.find((role) => role.id === roleId)?.name ?? roleId),
    last_login_time: i === 0 ? '2026-06-10T08:12:00+08:00' : `2026-06-${String(1 + (i % 9)).padStart(2, '0')}T1${i % 10}:0${i % 6}:00+08:00`,
    create_time: `2026-0${1 + (i % 5)}-${String(3 + (i % 25)).padStart(2, '0')}T10:00:00+08:00`,
    ...overrides,
  };
}

export const mockUsers: User[] = [
  makeUser(0, {
    id: 'u-admin',
    username: 'admin',
    nickname: '管理员',
    is_admin: true,
    is_super: true,
    status: 'enabled',
    dept_id: 'dept-root',
    dept_name: '沙盒网络',
    post_id: null,
    role_ids: ['role-super'],
    last_login_time: '2026-06-10T08:12:00+08:00',
  }),
  makeUser(1, {
    id: 'u-newbie',
    username: 'newbie',
    nickname: '新同事',
    must_change_password: true,
    dept_id: 'dept-frontend',
    dept_name: '前端组',
    post_id: 'post-2',
    role_ids: ['role-tech-lead'],
  }),
  makeUser(2, {
    id: 'u-zhangsan',
    username: 'zhangsan',
    nickname: '张三',
    dept_id: 'dept-backend',
    dept_name: '后端组',
    post_id: 'post-1',
    role_ids: ['role-tech-lead'],
  }),
  makeUser(3, {
    id: 'u-lisi',
    username: 'lisi',
    nickname: '李四',
    dept_id: 'dept-frontend',
    dept_name: '前端组',
    post_id: 'post-2',
    role_ids: ['role-tech-lead'],
  }),
  makeUser(4, {
    id: 'u-wangwu',
    username: 'wangwu',
    nickname: '王五',
    dept_id: 'dept-product',
    dept_name: '产品部',
    post_id: 'post-3',
    role_ids: ['role-ops'],
  }),
  // 没有登录账号的用户：只留手机号登录，列表不显示第二行灰字。
  makeUser(30, {
    id: 'u-phoneonly',
    username: null,
    nickname: '无账号',
    dept_id: 'dept-ops',
    dept_name: '运营部',
    post_id: null,
    role_ids: ['role-brand'],
  }),
  ...Array.from({ length: 22 }, (_, i) => makeUser(i + 5)),
];

export const mockPermissionTree: PermissionGroup[] = [
  {
    group_key: 'system',
    group_title: '系统管理',
    menus: [
      {
        menu_key: 'system:user',
        title: '用户管理',
        actions: [
          { code: 'system:user:index', action: 'index', label: '查看' },
          { code: 'system:user:create', action: 'create', label: '新增' },
          { code: 'system:user:update', action: 'update', label: '编辑' },
          { code: 'system:user:delete', action: 'delete', label: '删除' },
          { code: 'system:user:reset-password', action: 'reset-password', label: '重置密码' },
          { code: 'system:user:assign-role', action: 'assign-role', label: '分配角色' },
          { code: 'system:user:export', action: 'export', label: '导出' },
        ],
      },
      {
        menu_key: 'system:dept',
        title: '部门管理',
        actions: [
          { code: 'system:dept:index', action: 'index', label: '查看' },
          { code: 'system:dept:create', action: 'create', label: '新增' },
          { code: 'system:dept:update', action: 'update', label: '编辑' },
          { code: 'system:dept:delete', action: 'delete', label: '删除' },
        ],
      },
      {
        menu_key: 'system:post',
        title: '岗位管理',
        actions: [
          { code: 'system:post:index', action: 'index', label: '查看' },
          { code: 'system:post:create', action: 'create', label: '新增' },
          { code: 'system:post:update', action: 'update', label: '编辑' },
          { code: 'system:post:delete', action: 'delete', label: '删除' },
        ],
      },
      {
        menu_key: 'system:role',
        title: '角色权限',
        actions: [
          { code: 'system:role:index', action: 'index', label: '查看' },
          { code: 'system:role:create', action: 'create', label: '新增' },
          { code: 'system:role:update', action: 'update', label: '编辑' },
          { code: 'system:role:delete', action: 'delete', label: '删除' },
          { code: 'system:role:permission:index', action: 'permission-index', label: '查看权限' },
          { code: 'system:role:permission:update', action: 'permission-update', label: '保存权限' },
        ],
      },
      {
        menu_key: 'system:menu',
        title: '菜单管理',
        actions: [
          { code: 'system:menu:index', action: 'index', label: '查看' },
          { code: 'system:menu:create', action: 'create', label: '新增' },
          { code: 'system:menu:update', action: 'update', label: '编辑' },
          { code: 'system:menu:delete', action: 'delete', label: '删除' },
          { code: 'system:menu:reset', action: 'reset', label: '恢复默认' },
        ],
      },
      {
        menu_key: 'system:task',
        title: '任务中心',
        actions: [{ code: 'system:task:index', action: 'index', label: '查看' }],
      },
      {
        menu_key: 'system:monitor',
        title: '运行状态',
        actions: [{ code: 'system:monitor:index', action: 'index', label: '查看' }],
      },
      {
        menu_key: 'system:login-log',
        title: '登录日志',
        actions: [
          { code: 'system:login-log:index', action: 'index', label: '查看' },
          { code: 'system:login-log:export', action: 'export', label: '导出' },
        ],
      },
      {
        menu_key: 'system:audit-log',
        title: '操作日志',
        actions: [
          { code: 'system:audit-log:index', action: 'index', label: '查看' },
          { code: 'system:audit-log:export', action: 'export', label: '导出' },
        ],
      },
      {
        menu_key: 'system:cron',
        title: '定时任务',
        actions: [
          { code: 'system:cron:index', action: 'index', label: '查看' },
          { code: 'system:cron:update', action: 'update', label: '编辑' },
          { code: 'system:cron:run', action: 'run', label: '立即执行' },
        ],
      },
      {
        menu_key: 'system:plugin',
        title: '插件管理',
        actions: [
          { code: 'system:plugin:index', action: 'index', label: '查看' },
          { code: 'system:plugin:install', action: 'install', label: '安装' },
          { code: 'system:plugin:upgrade', action: 'upgrade', label: '升级' },
          { code: 'system:plugin:uninstall', action: 'uninstall', label: '卸载' },
          { code: 'system:plugin:upload', action: 'upload', label: '上传' },
        ],
      },
      {
        menu_key: 'system:update',
        title: '系统更新',
        actions: [
          { code: 'system:update:index', action: 'index', label: '查看' },
          { code: 'system:update:check', action: 'check', label: '检查更新' },
          { code: 'system:update:start', action: 'start', label: '开始升级' },
          { code: 'system:update:upload', action: 'upload', label: '上传升级包' },
        ],
      },
      {
        menu_key: 'system:config',
        title: '系统设置',
        actions: [
          { code: 'system:config:index', action: 'index', label: '查看' },
          { code: 'system:config:update', action: 'update', label: '修改' },
        ],
      },
    ],
  },
];

export function buildMenuTree(): MenuTree {
  return {
    menu_version: 7,
    items: [
      {
        key: 'home',
        type: 'page',
        title: '工作台',
        icon: 'home',
        source: { kind: 'system' },
        visible: true,
        locked: { move: true, hide: true },
        url: '/',
      },
      {
        key: 'org',
        type: 'group',
        title: '组织与用户',
        icon: 'users',
        source: { kind: 'system' },
        visible: true,
        locked: { move: false, hide: false },
        children: [
          { key: 'system:user', type: 'page', title: '用户管理', icon: 'user', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/user' },
          { key: 'system:dept', type: 'page', title: '部门管理', icon: 'building', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/dept' },
          { key: 'system:post', type: 'page', title: '岗位管理', icon: 'idcard', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/post' },
          { key: 'system:role', type: 'page', title: '角色权限', icon: 'safe', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/role' },
          { key: 'system:menu', type: 'page', title: '菜单管理', icon: 'menu', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/menu' },
        ],
      },
      {
        key: 'data',
        type: 'group',
        title: '数据管理',
        icon: 'list',
        source: { kind: 'system' },
        visible: true,
        locked: { move: false, hide: false },
        children: [
          { key: 'system:dict', type: 'page', title: '选项管理', icon: 'list', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/data/dict' },
          { key: 'system:file', type: 'page', title: '文件管理', icon: 'folder', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/data/file' },
          { key: 'system:announcement', type: 'page', title: '公告管理', icon: 'bell', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/data/notice' },
        ],
      },
      {
        key: 'system-settings',
        type: 'group',
        title: '系统',
        icon: 'setting',
        source: { kind: 'system' },
        visible: true,
        locked: { move: false, hide: false },
        children: [
          { key: 'system:monitor', type: 'page', title: '运行状态', icon: 'pulse', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/monitor' },
          { key: 'system:login-log', type: 'page', title: '登录日志', icon: 'clock', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/login-log' },
          { key: 'system:audit-log', type: 'page', title: '操作日志', icon: 'list', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/oper-log' },
          { key: 'system:cron', type: 'page', title: '定时任务', icon: 'timer', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/cron' },
          { key: 'preferences', type: 'page', title: '偏好设置', icon: 'setting', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/preferences' },
          { key: 'custom:docs', type: 'link', title: '帮助文档', icon: null, source: { kind: 'custom' }, visible: true, locked: { move: false, hide: false }, url: 'https://docs.example.com' },
          { key: 'system:plugin', type: 'page', title: '插件管理', icon: 'layers', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/plugins' },
          { key: 'system:update', type: 'page', title: '系统更新', icon: 'refresh', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/update' },
          { key: 'system:config', type: 'page', title: '系统设置', icon: 'setting', source: { kind: 'system' }, visible: true, locked: { move: false, hide: false }, url: '/system/settings' },
        ],
      },
      {
        key: 'plugin:analytics',
        type: 'page',
        title: '数据看板',
        icon: 'chart',
        source: { kind: 'plugin', plugin_label: '统计插件' },
        visible: false,
        locked: { move: false, hide: false },
        url: '/plugin/analytics',
      },
    ],
  };
}

export let mockMenuTree: MenuTree = buildMenuTree();

/** `/menus/reset` 语义：丢弃自定义节点与覆盖层，回到内置树。 */
export function restoreMenuTree(): void {
  mockMenuTree = buildMenuTree();
}

export interface RolePermissionsState {
  data_scope: string;
  dept_ids: string[];
  permission_codes: string[];
  base_version: number;
}

// 「品牌推广」的指定部门只有前端组：删前端组时 delete-check 会把它列进 affected_roles。
const seededRolePermissions: [string, RolePermissionsState][] = [
  [
    'role-brand',
    {
      data_scope: 'custom',
      dept_ids: ['dept-frontend'],
      permission_codes: ['system:user:index'],
      base_version: 1,
    },
  ],
];

export const mockRolePermissions = new Map<string, RolePermissionsState>(seededRolePermissions);

export interface MockTask extends Task {
  /** 内部字段：任务到达最终状态前还要被 GET 轮询的次数。 */
  pollsUntilDone: number;
}

export const mockTasks: MockTask[] = [
  {
    id: 'task-001',
    type: 'user.export',
    state: 'succeeded',
    progress: 100,
    stage: 'done',
    result: { file_id: 'file-users-001' },
    error: null,
    create_time: '2026-06-09T15:00:00+08:00',
    start_time: '2026-06-09T15:00:05+08:00',
    finish_time: '2026-06-09T15:00:42+08:00',
    pollsUntilDone: 0,
  },
  {
    id: 'task-002',
    type: 'user.export',
    state: 'failed',
    progress: 40,
    stage: 'error',
    result: null,
    error: { code: 'INTERNAL_ERROR', message: '导出数据量超出限制' },
    create_time: '2026-06-08T11:20:00+08:00',
    start_time: '2026-06-08T11:20:03+08:00',
    finish_time: '2026-06-08T11:20:30+08:00',
    pollsUntilDone: 0,
  },
];

export function findDept(depts: Dept[], id: string): Dept | undefined {
  for (const dept of depts) {
    if (dept.id === id) return dept;
    const hit = findDept(dept.children, id);
    if (hit) return hit;
  }
  return undefined;
}

export function flattenDepts(depts: Dept[]): Dept[] {
  return depts.flatMap((d) => [d, ...flattenDepts(d.children)]);
}

let taskSeq = 3;
export function createExportTask(): MockTask {
  const id = `task-${String(taskSeq).padStart(3, '0')}`;
  taskSeq += 1;
  const task: MockTask = {
    id,
    type: 'user.export',
    state: 'queued',
    progress: 0,
    stage: 'queued',
    result: null,
    error: null,
    create_time: new Date().toISOString(),
    start_time: null,
    finish_time: null,
    pollsUntilDone: 2,
  };
  mockTasks.unshift(task);
  return task;
}

/** 任务每被轮询一次前进一格（queued → running → succeeded）；返回该任务。 */
export function pollTask(task: MockTask): MockTask {
  if (task.state === 'queued' || task.state === 'running') {
    task.pollsUntilDone -= 1;
    if (task.pollsUntilDone <= 0) {
      task.state = 'succeeded';
      task.progress = 100;
      task.stage = 'done';
      task.result = { file_id: `file-${task.id}` };
      task.start_time = task.start_time ?? new Date().toISOString();
      task.finish_time = new Date().toISOString();
    } else {
      task.state = 'running';
      task.progress = 55;
      task.stage = 'exporting';
      task.start_time = task.start_time ?? new Date().toISOString();
    }
  }
  return task;
}

// 模块加载时的快照，供测试完整重置被改动的状态。
const initialUsers = structuredClone(mockUsers);
const initialPosts = structuredClone(mockPosts);
const initialRoles = structuredClone(mockRoles);
const initialDepts = structuredClone(mockDepts);

/** 其他 mock 数据模块（如 lifecycle.ts）注册的清理函数；避免反向 import 成环。 */
const extraResetters: Array<() => void> = [];
export function registerV1Resetter(fn: () => void): void {
  extraResetters.push(fn);
}

export function resetV1Mock(): void {
  authzVersionCounter = 1;
  mockMenuTree = buildMenuTree();
  for (const fn of extraResetters) fn();
  mockRolePermissions.clear();
  for (const [roleId, state] of seededRolePermissions) {
    mockRolePermissions.set(roleId, structuredClone(state));
  }
  mockUsers.splice(0, mockUsers.length, ...structuredClone(initialUsers));
  mockPosts.splice(0, mockPosts.length, ...structuredClone(initialPosts));
  mockRoles.splice(0, mockRoles.length, ...structuredClone(initialRoles));
  mockDepts.splice(0, mockDepts.length, ...structuredClone(initialDepts));
  mockTasks.splice(
    0,
    mockTasks.length,
    ...mockTasks.filter((t) => t.id === 'task-001' || t.id === 'task-002'),
  );
  taskSeq = 3;
}

// 聚合器会拍平每个兄弟文件的 default export——数据模块不贡献 handler。
export default [] satisfies import('msw').HttpHandler[];
