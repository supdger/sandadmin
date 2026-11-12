import type { HttpHandler } from 'msw';
import { http } from 'msw';

import type { Dept, DeptWrite } from '@/api/v1/depts';
import type { Role } from '@/api/v1/roles';
import { failV1, okV1, queryParam, requirePermission, v1Base } from './_v1';
import { flattenDepts, mockDepts, mockRolePermissions, mockRoles, mockUsers } from './_v1data';

const DEPT_PERMS = {
  index: 'system:dept:index',
  create: 'system:dept:create',
  update: 'system:dept:update',
  remove: 'system:dept:delete',
} as const;

function findDept(id: string): Dept | undefined {
  return flattenDepts(mockDepts).find((d) => d.id === id);
}

function deptNameTaken(name: string, exceptId?: string): boolean {
  return flattenDepts(mockDepts).some((d) => d.name === name && d.id !== exceptId);
}

function validate(body: Partial<DeptWrite>): string | null {
  if (!body.name?.trim()) return '部门名称不能为空';
  return null;
}

function removeFromTree(depts: Dept[], id: string): boolean {
  const idx = depts.findIndex((d) => d.id === id);
  if (idx >= 0) {
    depts.splice(idx, 1);
    return true;
  }
  return depts.some((d) => removeFromTree(d.children, id));
}

/** 把节点从树上摘下来（不改变其 parent_id 字段，调用方负责回填）。 */
function detachDept(depts: Dept[], id: string): Dept | undefined {
  const idx = depts.findIndex((d) => d.id === id);
  if (idx >= 0) {
    return depts.splice(idx, 1)[0];
  }
  for (const node of depts) {
    const found = detachDept(node.children, id);
    if (found) {
      return found;
    }
  }
  return undefined;
}

function insertDept(depts: Dept[], dept: Dept): void {
  const parent = dept.parent_id ? findDept(dept.parent_id) : undefined;
  if (parent) {
    parent.children.push(dept);
    parent.children.sort((a, b) => a.sort - b.sort);
  } else {
    depts.push(dept);
    depts.sort((a, b) => a.sort - b.sort);
  }
}

/** 负责人冗余字段：列表直接展示，不用再联查用户。 */
function leaderFields(leaderId: string | null) {
  const leader = leaderId ? mockUsers.find((user) => user.id === leaderId) : undefined;
  return {
    leader_id: leaderId,
    leader_name: leader?.nickname ?? null,
    leader_avatar: leader?.avatar ?? null,
    leader_status: leader?.status ?? null,
  };
}

/** 「指定部门」只剩这一个部门的角色：删完后它们的数据范围会降级成「只看自己的」。 */
function rolesOnlyOnDept(deptId: string): Role[] {
  return mockRoles.filter((role) => {
    const stored = mockRolePermissions.get(role.id);
    const scope = stored?.data_scope ?? role.data_scope;
    const deptIds = stored?.dept_ids ?? [];
    return scope === 'custom' && deptIds.length === 1 && deptIds[0] === deptId;
  });
}

/** 删除成功时同步降级受影响角色（契约：与删除同事务改成「只看自己的」）。 */
function downgradeAffectedRoles(deptId: string): void {
  for (const role of rolesOnlyOnDept(deptId)) {
    const stored = mockRolePermissions.get(role.id);
    role.data_scope = 'self';
    mockRolePermissions.set(role.id, {
      data_scope: 'self',
      dept_ids: [],
      permission_codes: stored?.permission_codes ?? [],
      base_version: (stored?.base_version ?? 1) + 1,
    });
  }
}

export default [
  http.get(`${v1Base}/depts/tree`, ({ request }) => {
    const auth = requirePermission(request, DEPT_PERMS.index);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const keyword = queryParam(url, 'keyword');
    const status = queryParam(url, 'status');
    if (keyword === '__error__') {
      return failV1(500, 'INTERNAL_ERROR', '部门树加载失败，请稍后再试');
    }
    const filter = (nodes: Dept[]): Dept[] =>
      nodes
        .filter((dept) => (status ? dept.status === status : true))
        .map((dept) => {
          const children = filter(dept.children);
          const selfMatch = !keyword || dept.name.includes(keyword);
          if (keyword && !selfMatch && children.length === 0) return null;
          return { ...dept, children };
        })
        .filter((dept): dept is Dept => dept !== null);
    return okV1({ items: filter(mockDepts) });
  }),

  http.post(`${v1Base}/depts`, async ({ request }) => {
    const auth = requirePermission(request, DEPT_PERMS.create);
    if ('response' in auth) return auth.response;
    const body = (await request.json()) as DeptWrite;
    const invalid = validate(body);
    if (invalid) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', { name: [invalid] });
    }
    if (deptNameTaken(body.name.trim())) {
      return failV1(409, 'DEPT_NAME_TAKEN', '同级下已存在同名部门，请换一个名称', {
        name: ['同级下已存在同名部门，请换一个名称'],
      });
    }
    const dept: Dept = {
      id: `dept-${Date.now().toString(36)}`,
      parent_id: body.parent_id ?? null,
      name: body.name.trim(),
      ...leaderFields(body.leader_id ?? null),
      phone: body.phone ?? null,
      sort: body.sort ?? 0,
      status: body.status ?? 'enabled',
      user_count: 0,
      children: [],
    };
    const parent = body.parent_id ? findDept(body.parent_id) : undefined;
    if (body.parent_id && !parent) {
      return failV1(404, 'DEPT_NOT_FOUND', '上级部门不存在');
    }
    if (parent) {
      parent.children.push(dept);
      parent.children.sort((a, b) => a.sort - b.sort);
    } else {
      mockDepts.push(dept);
    }
    return okV1(dept, { status: 201 });
  }),

  http.put(`${v1Base}/depts/:deptId`, async ({ request, params }) => {
    const auth = requirePermission(request, DEPT_PERMS.update);
    if ('response' in auth) return auth.response;
    const dept = findDept(String(params.deptId));
    if (!dept) {
      return failV1(404, 'DEPT_NOT_FOUND', '要操作的部门不存在或已被删除');
    }
    const body = (await request.json()) as DeptWrite;
    const invalid = validate(body);
    if (invalid) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', { name: [invalid] });
    }
    if (deptNameTaken(body.name.trim(), dept.id)) {
      return failV1(409, 'DEPT_NAME_TAKEN', '同级下已存在同名部门，请换一个名称', {
        name: ['同级下已存在同名部门，请换一个名称'],
      });
    }

    // parent_id 显式传 null 表示挪到顶层；不传则保持原上级。
    const nextParentId = 'parent_id' in body ? (body.parent_id ?? null) : dept.parent_id;
    if (nextParentId) {
      if (nextParentId === dept.id) {
        return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
          parent_id: ['不能把部门挪到它自己下面'],
        });
      }
      const parent = findDept(nextParentId);
      if (!parent) {
        return failV1(404, 'DEPT_NOT_FOUND', '上级部门不存在');
      }
      if (flattenDepts([dept]).some((d) => d.id === nextParentId)) {
        return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
          parent_id: ['不能把部门挪到它自己的下级部门里'],
        });
      }
    }

    const leaderId = 'leader_id' in body ? (body.leader_id ?? null) : dept.leader_id;
    const detached = detachDept(mockDepts, dept.id);
    if (!detached) {
      return failV1(404, 'DEPT_NOT_FOUND', '要操作的部门不存在或已被删除');
    }
    Object.assign(dept, {
      parent_id: nextParentId,
      name: body.name.trim(),
      ...leaderFields(leaderId),
      phone: 'phone' in body ? (body.phone ?? null) : dept.phone,
      sort: body.sort ?? dept.sort,
      status: body.status ?? dept.status,
    });
    insertDept(mockDepts, dept);
    return okV1(dept);
  }),

  http.get(`${v1Base}/depts/:deptId/delete-check`, ({ request, params }) => {
    const auth = requirePermission(request, DEPT_PERMS.remove);
    if ('response' in auth) return auth.response;
    const dept = findDept(String(params.deptId));
    if (!dept) {
      return failV1(404, 'DEPT_NOT_FOUND', '要操作的部门不存在或已被删除');
    }
    return okV1({
      user_count: dept.user_count,
      child_count: dept.children.length,
      affected_roles: rolesOnlyOnDept(dept.id).map((role) => ({ id: role.id, name: role.name })),
    });
  }),

  http.delete(`${v1Base}/depts/:deptId`, ({ request, params }) => {
    const auth = requirePermission(request, DEPT_PERMS.remove);
    if ('response' in auth) return auth.response;
    const dept = findDept(String(params.deptId));
    if (!dept) {
      return failV1(404, 'DEPT_NOT_FOUND', '要操作的部门不存在或已被删除');
    }
    if (dept.children.length > 0 || dept.user_count > 0) {
      return failV1(409, 'DEPT_NOT_EMPTY', '部门下还有子部门或成员，不能删除');
    }
    downgradeAffectedRoles(dept.id);
    removeFromTree(mockDepts, dept.id);
    return okV1({});
  }),
] satisfies HttpHandler[];
