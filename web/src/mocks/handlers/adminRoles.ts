import type { HttpHandler } from 'msw';
import { http } from 'msw';

import type { Role, RolePermissions, RolePermissionsWrite, RoleWrite } from '@/api/v1/roles';
import { failV1, okV1, queryPage, queryParam, requirePermission, v1Base } from './_v1';
import {
  bumpAuthzVersion,
  mockPermissionTree,
  mockRolePermissions,
  mockRoles,
  mockUsers,
} from './_v1data';

const ROLE_PERMS = {
  index: 'system:role:index',
  create: 'system:role:create',
  update: 'system:role:update',
  remove: 'system:role:delete',
  permIndex: 'system:role:permission:index',
  permUpdate: 'system:role:permission:update',
} as const;

function findRole(id: string): Role | undefined {
  return mockRoles.find((r) => r.id === id);
}

function roleNameTaken(name: string, exceptId?: string): boolean {
  return mockRoles.some((r) => r.name === name && r.id !== exceptId);
}

function permissionsOf(roleId: string): RolePermissions {
  const stored = mockRolePermissions.get(roleId);
  const role = findRole(roleId);
  return {
    data_scope: (stored?.data_scope ?? role?.data_scope ?? 'self') as RolePermissions['data_scope'],
    dept_ids: stored?.dept_ids ?? [],
    permission_codes: stored?.permission_codes ?? [],
    user_count: role?.user_count ?? 0,
    readonly: Boolean(role?.is_system || role?.is_super),
    base_version: stored?.base_version ?? 1,
  };
}

export default [
  http.get(`${v1Base}/roles`, ({ request }) => {
    const auth = requirePermission(request, ROLE_PERMS.index);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    if (queryParam(url, 'keyword') === '__error__') {
      return failV1(500, 'INTERNAL_ERROR', '角色列表加载失败，请稍后再试');
    }
    const keyword = queryParam(url, 'keyword');
    const status = queryParam(url, 'status');
    const filtered = mockRoles.filter((r) => {
      if (keyword && !r.name.includes(keyword)) return false;
      if (status && r.status !== status) return false;
      return true;
    });
    const { page, page_size } = queryPage(url);
    const start = (page - 1) * page_size;
    return okV1({
      items: filtered.slice(start, start + page_size),
      total: filtered.length,
      page,
      page_size,
    });
  }),

  http.post(`${v1Base}/roles`, async ({ request }) => {
    const auth = requirePermission(request, ROLE_PERMS.create);
    if ('response' in auth) return auth.response;
    const body = (await request.json()) as RoleWrite;
    if (!body.name?.trim()) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        name: ['角色名称不能为空'],
      });
    }
    if (roleNameTaken(body.name.trim())) {
      return failV1(409, 'ROLE_NAME_TAKEN', '这个角色名称已经有人用了，请换一个', {
        name: ['这个角色名称已经有人用了，请换一个'],
      });
    }
    const role: Role = {
      id: `role-${Date.now().toString(36)}`,
      name: body.name.trim(),
      remark: body.remark ?? null,
      is_system: false,
      is_super: false,
      status: body.status ?? 'enabled',
      data_scope: 'self',
      user_count: 0,
      update_time: new Date().toISOString(),
    };
    mockRoles.push(role);
    return okV1(role, { status: 201 });
  }),

  http.get(`${v1Base}/roles/:roleId`, ({ request, params }) => {
    const auth = requirePermission(request, ROLE_PERMS.index);
    if ('response' in auth) return auth.response;
    const role = findRole(String(params.roleId));
    if (!role) {
      return failV1(404, 'NOT_FOUND', '要操作的角色不存在或已被删除');
    }
    return okV1(role);
  }),

  http.put(`${v1Base}/roles/:roleId`, async ({ request, params }) => {
    const auth = requirePermission(request, ROLE_PERMS.update);
    if ('response' in auth) return auth.response;
    const role = findRole(String(params.roleId));
    if (!role) {
      return failV1(404, 'NOT_FOUND', '要操作的角色不存在或已被删除');
    }
    if (role.is_system || role.is_super) {
      return failV1(409, 'SYSTEM_ROLE_READONLY', '系统内置角色不允许修改');
    }
    const body = (await request.json()) as RoleWrite;
    if (!body.name?.trim()) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        name: ['角色名称不能为空'],
      });
    }
    if (roleNameTaken(body.name.trim(), role.id)) {
      return failV1(409, 'ROLE_NAME_TAKEN', '这个角色名称已经有人用了，请换一个', {
        name: ['这个角色名称已经有人用了，请换一个'],
      });
    }
    Object.assign(role, {
      name: body.name.trim(),
      remark: body.remark ?? role.remark,
      status: body.status ?? role.status,
      update_time: new Date().toISOString(),
    });
    return okV1(role);
  }),

  http.delete(`${v1Base}/roles/:roleId`, ({ request, params }) => {
    const auth = requirePermission(request, ROLE_PERMS.remove);
    if ('response' in auth) return auth.response;
    const role = findRole(String(params.roleId));
    if (!role) {
      return failV1(404, 'NOT_FOUND', '要操作的角色不存在或已被删除');
    }
    if (role.is_system || role.is_super) {
      return failV1(409, 'SYSTEM_ROLE_READONLY', '系统内置角色不允许删除');
    }
    // 在用角色删除时，把成员迁到默认「只读访客」角色。
    let moved = 0;
    for (const member of mockUsers) {
      const idx = member.role_ids.indexOf(role.id);
      if (idx >= 0) {
        member.role_ids[idx] = 'role-viewer';
        moved += 1;
      }
    }
    mockRoles.splice(mockRoles.indexOf(role), 1);
    if (moved > 0) bumpAuthzVersion();
    return okV1({ moved_to_default: moved });
  }),

  http.post(`${v1Base}/roles/:roleId/copy`, async ({ request, params }) => {
    const auth = requirePermission(request, ROLE_PERMS.create);
    if ('response' in auth) return auth.response;
    const role = findRole(String(params.roleId));
    if (!role) {
      return failV1(404, 'NOT_FOUND', '要操作的角色不存在或已被删除');
    }
    const body = (await request.json()) as { name?: string };
    const name = body.name?.trim() ?? '';
    if (!name) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        name: ['角色名称不能为空'],
      });
    }
    if (roleNameTaken(name)) {
      return failV1(409, 'ROLE_NAME_TAKEN', '这个角色名称已经有人用了，请换一个', {
        name: ['这个角色名称已经有人用了，请换一个'],
      });
    }
    const copy: Role = {
      ...role,
      id: `role-${Date.now().toString(36)}`,
      name,
      is_system: false,
      is_super: false,
      user_count: 0,
      update_time: new Date().toISOString(),
    };
    const stored = mockRolePermissions.get(role.id);
    if (stored) {
      mockRolePermissions.set(copy.id, { ...stored, base_version: 1 });
    }
    mockRoles.push(copy);
    return okV1(copy, { status: 201 });
  }),

  http.put(`${v1Base}/roles/:roleId/status`, async ({ request, params }) => {
    const auth = requirePermission(request, ROLE_PERMS.update);
    if ('response' in auth) return auth.response;
    const role = findRole(String(params.roleId));
    if (!role) {
      return failV1(404, 'NOT_FOUND', '要操作的角色不存在或已被删除');
    }
    if (role.is_system || role.is_super) {
      return failV1(409, 'SYSTEM_ROLE_READONLY', '系统内置角色不允许修改');
    }
    const body = (await request.json()) as { status: 'enabled' | 'disabled' };
    role.status = body.status;
    bumpAuthzVersion();
    return okV1({});
  }),

  http.get(`${v1Base}/roles/:roleId/permissions`, ({ request, params }) => {
    const auth = requirePermission(request, ROLE_PERMS.permIndex);
    if ('response' in auth) return auth.response;
    const role = findRole(String(params.roleId));
    if (!role) {
      return failV1(404, 'NOT_FOUND', '要操作的角色不存在或已被删除');
    }
    return okV1(permissionsOf(role.id));
  }),

  http.put(`${v1Base}/roles/:roleId/permissions`, async ({ request, params }) => {
    const auth = requirePermission(request, ROLE_PERMS.permUpdate);
    if ('response' in auth) return auth.response;
    const role = findRole(String(params.roleId));
    if (!role) {
      return failV1(404, 'NOT_FOUND', '要操作的角色不存在或已被删除');
    }
    if (role.is_system || role.is_super) {
      return failV1(409, 'SYSTEM_ROLE_READONLY', '系统内置角色不允许修改');
    }
    const body = (await request.json()) as RolePermissionsWrite;
    const current = permissionsOf(role.id);
    if (body.base_version !== current.base_version) {
      return failV1(409, 'STALE_VERSION', '权限配置已被他人修改，请刷新后重试', {
        base_version: ['权限配置已被他人修改，请刷新后重试'],
      });
    }
    mockRolePermissions.set(role.id, {
      data_scope: body.data_scope,
      dept_ids: body.dept_ids ?? [],
      permission_codes: body.permission_codes ?? [],
      base_version: current.base_version + 1,
    });
    bumpAuthzVersion();
    return okV1({ affected_users: role.user_count });
  }),

  http.get(`${v1Base}/permission-tree`, ({ request }) => {
    const auth = requirePermission(request, ROLE_PERMS.permIndex);
    if ('response' in auth) return auth.response;
    return okV1({ items: mockPermissionTree });
  }),
] satisfies HttpHandler[];
