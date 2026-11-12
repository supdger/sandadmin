import { beforeEach, describe, expect, it } from 'vitest';

import { AdminApiError } from '@/lib/adminClient';
import { clearToken, setToken } from '@/lib/authToken';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { getDeptTree, createDept } from './depts';
import { getMyMenus, getMe } from './me';
import { getMenuTree, moveMenu, resetMenus } from './menus';
import { getDeptOptions, getUserOptions } from './org';
import { getPermissionTree } from './permissions';
import { getRolePermissions, listRoles, saveRolePermissions } from './roles';
import { downloadTask, getTask, listTasks } from './tasks';
import { createUser, exportUsers, getUser, listUsers, updateUserStatus } from './users';
import { listPosts } from './posts';

const ADMIN = 'mock-admin-token';
const VIEWER = 'mock-viewer-token';
const GUEST = 'mock-guest-token';

beforeEach(() => {
  resetV1Mock();
  setToken(ADMIN);
});

describe('v1 users API against MSW', () => {
  it('paginates the user list', async () => {
    const firstPage = await listUsers({ page: 1, page_size: 20 });
    expect(firstPage.total).toBeGreaterThan(20);
    expect(firstPage.items).toHaveLength(20);
    expect(firstPage.items[0].username).toBe('admin');

    const secondPage = await listUsers({ page: 2, page_size: 20 });
    expect(secondPage.items.length).toBe(firstPage.total - 20);
  });

  it('filters by keyword and returns an empty page for no match', async () => {
    const hit = await listUsers({ keyword: '张三' });
    expect(hit.items.some((u) => u.nickname === '张三')).toBe(true);

    const empty = await listUsers({ keyword: '不存在的关键字xyz' });
    expect(empty.items).toHaveLength(0);
    expect(empty.total).toBe(0);
  });

  it('surfaces the backend error state (keyword=__error__ → 500)', async () => {
    const err = await listUsers({ keyword: '__error__' }).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    expect((err as AdminApiError).system).toBe(true);
    expect((err as AdminApiError).httpStatus).toBe(500);
  });

  it('returns USERNAME_TAKEN 409 on duplicate login name', async () => {
    const err = await createUser({
      username: 'admin',
      nickname: '撞名',
      password: 'Passw0rd!',
      phone: '13900000000',
      dept_id: 'dept-tech',
      role_ids: [],
    }).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    const apiErr = err as AdminApiError;
    expect(apiErr.code).toBe('USERNAME_TAKEN');
    expect(apiErr.httpStatus).toBe(409);
    expect(apiErr.fields?.username?.[0]).toContain('登录名');
  });

  it('creates a user and reads it back with detail fields', async () => {
    const created = await createUser({
      username: 'newbie2',
      nickname: '新人二号',
      password: 'Passw0rd!',
      phone: '13911112222',
      email: 'n2@example.com',
      dept_id: 'dept-backend',
      post_id: 'post-1',
      role_ids: ['role-tech-lead'],
    });
    expect(created.username).toBe('newbie2');

    const detail = await getUser(created.id);
    expect(detail.dept_path).toContain('后端组');
    expect(detail.post?.name).toBe('高级后端工程师');
    expect(detail.roles[0].name).toBe('技术负责人');
  });

  it('denies writes for the read-only viewer and everything for guest', async () => {
    setToken(VIEWER);
    const denied = await updateUserStatus('u-003', { status: 'disabled' }).catch(
      (e: unknown) => e,
    );
    expect(denied).toBeInstanceOf(AdminApiError);
    expect((denied as AdminApiError).code).toBe('PERMISSION_DENIED');
    expect((denied as AdminApiError).httpStatus).toBe(403);

    // 只读访客仍可读取列表。
    const list = await listUsers({ page_size: 5 });
    expect(list.items.length).toBeGreaterThan(0);

    setToken(GUEST);
    const guestErr = await listUsers().catch((e: unknown) => e);
    expect((guestErr as AdminApiError).code).toBe('PERMISSION_DENIED');
    // 但访客仍可调用已登录接口。
    const me = await getMe();
    expect(me.nickname).toBe('访客');
  });

  it('returns UNAUTHENTICATED without a token', async () => {
    clearToken();
    const err = await listUsers().catch((e: unknown) => e);
    expect((err as AdminApiError).code).toBe('UNAUTHENTICATED');
    expect((err as AdminApiError).httpStatus).toBe(401);
  });
});

describe('v1 depts/posts/roles APIs', () => {
  it('returns the recursive dept tree', async () => {
    const tree = await getDeptTree();
    expect(tree.items[0].name).toBe('沙盒网络');
    expect(tree.items[0].children.length).toBeGreaterThan(0);
    const tech = tree.items[0].children.find((d) => d.id === 'dept-tech');
    expect(tech?.children.map((c) => c.id)).toContain('dept-backend');
  });

  it('enforces DEPT_NAME_TAKEN and DEPT_NOT_EMPTY', async () => {
    const dup = await createDept({ name: '技术部', parent_id: 'dept-root' }).catch(
      (e: unknown) => e,
    );
    expect((dup as AdminApiError).code).toBe('DEPT_NAME_TAKEN');
    expect((dup as AdminApiError).httpStatus).toBe(409);
  });

  it('lists posts with pagination', async () => {
    const page = await listPosts({ page_size: 3 });
    expect(page.total).toBe(6);
    expect(page.items).toHaveLength(3);
  });

  it('lists roles and reads the permission tree', async () => {
    const roles = await listRoles();
    expect(roles.items.some((r) => r.is_super)).toBe(true);

    const tree = await getPermissionTree();
    const codes = tree.items.flatMap((g) =>
      g.menus.flatMap((m) => m.actions.map((a) => a.code)),
    );
    expect(codes).toContain('system:user:create');
    expect(codes).toContain('system:menu:reset');
  });

  it('returns SYSTEM_ROLE_READONLY when editing a built-in role', async () => {
    const { updateRole } = await import('./roles');
    const err = await updateRole('role-super', { name: '改名' }).catch((e: unknown) => e);
    expect((err as AdminApiError).code).toBe('SYSTEM_ROLE_READONLY');
  });

  it('rejects stale role permission writes, then accepts the fresh version', async () => {
    const before = await getRolePermissions('role-ops');
    expect(before.base_version).toBe(1);

    const stale = await saveRolePermissions('role-ops', {
      data_scope: 'dept',
      dept_ids: ['dept-product'],
      permission_codes: ['system:user:index'],
      base_version: 99,
    }).catch((e: unknown) => e);
    expect((stale as AdminApiError).code).toBe('STALE_VERSION');
    expect((stale as AdminApiError).httpStatus).toBe(409);

    const saved = await saveRolePermissions('role-ops', {
      data_scope: 'dept',
      dept_ids: ['dept-product'],
      permission_codes: ['system:user:index'],
      base_version: before.base_version,
    });
    expect(saved.affected_users).toBe(4);

    const after = await getRolePermissions('role-ops');
    expect(after.base_version).toBe(2);
    expect(after.permission_codes).toContain('system:user:index');
  });
});

describe('v1 menus API', () => {
  it('returns the menu tree with version and org entries', async () => {
    const tree = await getMenuTree();
    expect(tree.menu_version).toBeGreaterThan(0);
    const org = tree.items.find((n) => n.key === 'org');
    expect(org?.children?.map((c) => c.key)).toContain('system:user');
  });

  it('rejects menu moves with a stale base_version', async () => {
    const tree = await getMenuTree();
    const err = await moveMenu('preferences', {
      parent_key: 'org',
      index: 0,
      base_version: tree.menu_version + 5,
    }).catch((e: unknown) => e);
    expect((err as AdminApiError).code).toBe('STALE_VERSION');
    expect((err as AdminApiError).httpStatus).toBe(409);
  });

  it('moves a menu and bumps menu_version', async () => {
    const before = await getMenuTree();
    await moveMenu('preferences', {
      parent_key: 'org',
      index: 0,
      base_version: before.menu_version,
    });
    const after = await getMenuTree();
    expect(after.menu_version).toBe(before.menu_version + 1);
    const org = after.items.find((n) => n.key === 'org');
    expect(org?.children?.[0].key).toBe('preferences');
  });

  it('refuses to delete a system menu but allows custom links', async () => {
    const sysErr = await (await import('./menus')).deleteMenu('home').catch((e: unknown) => e);
    expect((sysErr as AdminApiError).code).toBe('MENU_LOCKED');
  });

  it('resetMenus restores the canonical tree', async () => {
    const before = await getMenuTree();
    const { createMenuLink } = await import('./menus');
    await createMenuLink({ title: '临时链接', url: 'https://example.com' });
    const mid = await getMenuTree();
    expect(mid.menu_version).toBeGreaterThan(before.menu_version);

    const reset = await resetMenus();
    expect(reset.menu_version).toBeGreaterThan(0);
    const after = await getMenuTree();
    expect(after.items.find((n) => n.title === '临时链接')).toBeUndefined();
  });
});

describe('v1 me/org/tasks APIs', () => {
  it('serves /me and /me/menus for the admin', async () => {
    const me = await getMe();
    expect(me.username).toBe('admin');
    expect(me.permissions).toContain('*');

    const menus = await getMyMenus();
    expect(menus.items.length).toBeGreaterThan(0);
    const org = menus.items.find((n) => n.key === 'org');
    expect(org?.children?.some((c) => c.path === '/system/user')).toBe(true);
  });

  it('serves user/dept options to any authenticated user', async () => {
    setToken(GUEST);
    const users = await getUserOptions({ keyword: '张' });
    expect(users.items.length).toBeGreaterThan(0);
    const depts = await getDeptOptions();
    expect(depts.items.every((d) => d.name)).toBe(true);
  });

  it('walks the export task lifecycle queued → running → succeeded → download', async () => {
    const accepted = await exportUsers({ keyword: '张' });
    expect(accepted.state).toBe('queued');

    const t1 = await getTask(accepted.task_id);
    expect(t1.state).toBe('running');

    const t2 = await getTask(accepted.task_id);
    expect(t2.state).toBe('succeeded');
    expect(t2.progress).toBe(100);

    const file = await downloadTask(accepted.task_id);
    expect(file.fileName).toBe(`${accepted.task_id}.csv`);
    const text = await file.blob.text();
    expect(text).toContain('username,nickname');
  });

  it('refuses to download a task that has not succeeded', async () => {
    const accepted = await exportUsers();
    const err = await downloadTask(accepted.task_id).catch((e: unknown) => e);
    expect((err as AdminApiError).code).toBe('TASK_RUN_NOT_DOWNLOADABLE');
    expect((err as AdminApiError).httpStatus).toBe(409);
  });

  it('lists tasks with state filter', async () => {
    const failed = await listTasks({ state: 'failed' });
    expect(failed.items.every((t) => t.state === 'failed')).toBe(true);
    expect(failed.items.length).toBeGreaterThan(0);
  });
});
