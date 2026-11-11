import type { HttpHandler } from 'msw';
import { http } from 'msw';

import type { User, UserDetail, UserUpdate, UserWrite } from '@/api/v1/users';
import { failV1, okV1, queryPage, queryParam, requirePermission, v1Base } from './_v1';
import {
  createExportTask,
  flattenDepts,
  mockDepts,
  mockPosts,
  mockRoles,
  mockUsers,
  bumpAuthzVersion,
} from './_v1data';

const USER_PERMS = {
  index: 'system:user:index',
  create: 'system:user:create',
  update: 'system:user:update',
  remove: 'system:user:delete',
  reset: 'system:user:reset-password',
  assign: 'system:user:assign-role',
  export: 'system:user:export',
} as const;

function findUser(id: string): User | undefined {
  return mockUsers.find((u) => u.id === id);
}

/** 列表冗余的角色名/岗位名：与 post_id、role_ids 保持一致。 */
function nameFields(postId: string | null, roleIds: string[]) {
  return {
    post_name: mockPosts.find((post) => post.id === postId)?.name ?? null,
    role_names: roleIds.map(
      (roleId) => mockRoles.find((role) => role.id === roleId)?.name ?? roleId,
    ),
  };
}

function toDetail(user: User): UserDetail {
  const post = mockPosts.find((item) => item.id === user.post_id) ?? null;
  const roles = user.role_ids
    .map((roleId) => mockRoles.find((role) => role.id === roleId))
    .filter((role): role is NonNullable<typeof role> => Boolean(role))
    .map((role) => ({ id: role.id, name: role.name }));
  return {
    ...user,
    dept_path: user.dept_name ? `沙盒网络 / ${user.dept_name}` : null,
    post: post ? { id: post.id, name: post.name } : null,
    roles,
  };
}

function applyFilters(url: URL): User[] {
  const keyword = queryParam(url, 'keyword');
  const status = queryParam(url, 'status');
  const deptId = queryParam(url, 'dept_id');
  const roleId = queryParam(url, 'role_id');
  const postId = queryParam(url, 'post_id');
  return mockUsers.filter((u) => {
    if (keyword && keyword !== '__error__') {
      const hay = `${u.username ?? ''}${u.nickname}${u.phone}${u.email ?? ''}`;
      if (!hay.includes(keyword)) return false;
    }
    if (status && u.status !== status) return false;
    if (deptId && u.dept_id !== deptId) return false;
    if (roleId && !u.role_ids.includes(roleId)) return false;
    if (postId && u.post_id !== postId) return false;
    return true;
  });
}

function validateWrite(body: Partial<UserWrite>): string | null {
  if (!body.nickname?.trim()) return '昵称不能为空';
  if (body.phone && !/^1\d{10}$/.test(body.phone)) return '手机号格式不正确';
  if (body.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(body.email)) return '邮箱格式不正确';
  return null;
}

function usernameTaken(username: string, exceptId?: string): boolean {
  return mockUsers.some((u) => u.username === username && u.id !== exceptId);
}

export default [
  http.get(`${v1Base}/users`, ({ request }) => {
    const auth = requirePermission(request, USER_PERMS.index);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    // `keyword=__error__` 用来触发后端失败，测错误态。
    if (queryParam(url, 'keyword') === '__error__') {
      return failV1(500, 'INTERNAL_ERROR', '用户列表查询失败，请稍后再试');
    }
    const { page, page_size } = queryPage(url);
    const filtered = applyFilters(url);
    const start = (page - 1) * page_size;
    return okV1({
      items: filtered.slice(start, start + page_size),
      total: filtered.length,
      page,
      page_size,
    });
  }),

  http.post(`${v1Base}/users`, async ({ request }) => {
    const auth = requirePermission(request, USER_PERMS.create);
    if ('response' in auth) return auth.response;
    const body = (await request.json()) as Partial<UserWrite>;
    const invalid = validateWrite(body);
    if (invalid) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        nickname: [invalid],
      });
    }
    if (body.username && usernameTaken(body.username)) {
      return failV1(409, 'USERNAME_TAKEN', '这个登录名已经有人用了，请换一个', {
        username: ['这个登录名已经有人用了，请换一个'],
      });
    }
    const user: User = {
      id: `u-${Date.now().toString(36)}`,
      username: body.username ?? null,
      nickname: body.nickname!.trim(),
      avatar: null,
      avatar_file_id: null,
      phone: body.phone ?? '',
      email: body.email ?? null,
      status: body.status ?? 'enabled',
      is_admin: false,
      is_super: false,
      must_change_password: true,
      permissions: [],
      dept_id: body.dept_id!,
      dept_name:
        flattenDepts(mockDepts).find((dept) => dept.id === body.dept_id)?.name ?? null,
      post_id: body.post_id ?? null,
      role_ids: body.role_ids ?? [],
      ...nameFields(body.post_id ?? null, body.role_ids ?? []),
      last_login_time: null,
      create_time: new Date().toISOString(),
    };
    mockUsers.push(user);
    return okV1(user, { status: 201 });
  }),

  http.get(`${v1Base}/users/:userId`, ({ request, params }) => {
    const auth = requirePermission(request, USER_PERMS.index);
    if ('response' in auth) return auth.response;
    const user = findUser(String(params.userId));
    if (!user) {
      return failV1(404, 'USER_NOT_FOUND', '要操作的用户不存在或已被删除');
    }
    return okV1(toDetail(user));
  }),

  http.put(`${v1Base}/users/:userId`, async ({ request, params }) => {
    const auth = requirePermission(request, USER_PERMS.update);
    if ('response' in auth) return auth.response;
    const user = findUser(String(params.userId));
    if (!user) {
      return failV1(404, 'USER_NOT_FOUND', '要操作的用户不存在或已被删除');
    }
    const body = (await request.json()) as Partial<UserUpdate>;
    const invalid = validateWrite(body);
    if (invalid) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        nickname: [invalid],
      });
    }
    if (body.username && usernameTaken(body.username, user.id)) {
      return failV1(409, 'USERNAME_TAKEN', '这个登录名已经有人用了，请换一个', {
        username: ['这个登录名已经有人用了，请换一个'],
      });
    }
    const postId = 'post_id' in body ? (body.post_id ?? null) : user.post_id;
    const roleIds = body.role_ids ?? user.role_ids;
    Object.assign(user, {
      username: 'username' in body ? (body.username ?? null) : user.username,
      nickname: body.nickname?.trim() ?? user.nickname,
      phone: body.phone ?? user.phone,
      email: 'email' in body ? (body.email ?? null) : user.email,
      dept_id: body.dept_id ?? user.dept_id,
      post_id: postId,
      role_ids: roleIds,
      status: body.status ?? user.status,
      ...nameFields(postId, roleIds),
    });
    return okV1(user);
  }),

  http.delete(`${v1Base}/users/:userId`, async ({ request, params }) => {
    const auth = requirePermission(request, USER_PERMS.remove);
    if ('response' in auth) return auth.response;
    const user = findUser(String(params.userId));
    if (!user) {
      return failV1(404, 'USER_NOT_FOUND', '要操作的用户不存在或已被删除');
    }
    if (user.is_super) {
      return failV1(409, 'LAST_ADMIN', '至少要保留一个超级管理员，不能删除');
    }
    const body = (await request.json().catch(() => ({}))) as { transfer_to_user_id?: string | null };
    const idx = mockUsers.indexOf(user);
    if (idx >= 0) mockUsers.splice(idx, 1);
    // transfer_to_user_id 仅为契约对齐而接收；mock 不转交名下资源。
    void body;
    return okV1({});
  }),

  http.put(`${v1Base}/users/:userId/status`, async ({ request, params }) => {
    const auth = requirePermission(request, USER_PERMS.update);
    if ('response' in auth) return auth.response;
    const user = findUser(String(params.userId));
    if (!user) {
      return failV1(404, 'USER_NOT_FOUND', '要操作的用户不存在或已被删除');
    }
    const body = (await request.json()) as { status: 'enabled' | 'disabled' };
    if (user.is_super && body.status === 'disabled') {
      return failV1(409, 'LAST_ADMIN', '至少要保留一个超级管理员，不能停用');
    }
    user.status = body.status;
    bumpAuthzVersion();
    return okV1({});
  }),

  http.get(`${v1Base}/users/:userId/disable-check`, ({ request, params }) => {
    const auth = requirePermission(request, [USER_PERMS.update, USER_PERMS.remove]);
    if ('response' in auth) return auth.response;
    const user = findUser(String(params.userId));
    if (!user) {
      return failV1(404, 'USER_NOT_FOUND', '要操作的用户不存在或已被删除');
    }
    // 真实口径：该用户担任负责人的部门数；为空则无需转交。
    const ledDepts = flattenDepts(mockDepts).filter((d) => d.leader_id === user.id);
    const items = ledDepts.length
      ? [
          {
            source: 'dept',
            kind: 'leader',
            count: ledDepts.length,
            message: `担任 ${ledDepts.length} 个部门的负责人`,
          },
        ]
      : [];
    return okV1(items);
  }),

  http.post(`${v1Base}/users/:userId/reset-password`, async ({ request, params }) => {
    const auth = requirePermission(request, USER_PERMS.reset);
    if ('response' in auth) return auth.response;
    const user = findUser(String(params.userId));
    if (!user) {
      return failV1(404, 'USER_NOT_FOUND', '要操作的用户不存在或已被删除');
    }
    const body = (await request.json()) as { password?: string };
    if (!body.password || body.password.length < 6) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        password: ['密码至少 6 位'],
      });
    }
    user.must_change_password = true;
    return okV1({});
  }),

  http.put(`${v1Base}/users/:userId/roles`, async ({ request, params }) => {
    const auth = requirePermission(request, USER_PERMS.assign);
    if ('response' in auth) return auth.response;
    const user = findUser(String(params.userId));
    if (!user) {
      return failV1(404, 'USER_NOT_FOUND', '要操作的用户不存在或已被删除');
    }
    const body = (await request.json()) as { role_ids?: string[] };
    user.role_ids = body.role_ids ?? [];
    bumpAuthzVersion();
    return okV1({});
  }),

  http.post(`${v1Base}/users/export`, async ({ request }) => {
    const auth = requirePermission(request, USER_PERMS.export);
    if ('response' in auth) return auth.response;
    const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;
    void body; // filters accepted for contract parity
    const task = createExportTask();
    return okV1({ task_id: task.id, state: 'queued' }, { status: 202 });
  }),
] satisfies HttpHandler[];
