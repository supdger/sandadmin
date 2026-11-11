import type { HttpHandler } from 'msw';
import { http } from 'msw';

import type { MyMenuNode } from '@/api/v1/me';
import type { User } from '@/api/v1/users';
import { okV1, requireUser, v1Base } from './_v1';
import { flattenDepts, mockDepts, mockMenuTree, mockUsers } from './_v1data';

function principalToUser(principal: { username: string; nickname: string; permissions: string[] }): User {
  const found = mockUsers.find((u) => u.username === principal.username);
  if (found) {
    return { ...found, permissions: [...principal.permissions] };
  }
  return {
    id: `u-${principal.username}`,
    username: principal.username,
    nickname: principal.nickname,
    avatar: null,
    avatar_file_id: null,
    phone: '',
    email: null,
    status: 'enabled',
    is_admin: false,
    is_super: false,
    must_change_password: false,
    permissions: [...principal.permissions],
    dept_id: 'dept-root',
    dept_name: '沙盒网络',
    post_id: null,
    post_name: null,
    role_ids: [],
    role_names: [],
    last_login_time: null,
    create_time: '2026-01-01T00:00:00+08:00',
  };
}

// key → 需要的权限；未列出的节点（group/link）不设权限要求。
const MENU_PERMISSION: Record<string, string> = {
  home: 'workbench:view',
  'system:user': 'system:user:index',
  'system:dept': 'system:dept:index',
  'system:post': 'system:post:index',
  'system:role': 'system:role:index',
  'system:menu': 'system:menu:index',
  preferences: 'preferences:view',
  'system:dict': 'system:dict:index',
  'system:file': 'system:file:index',
  'system:announcement': 'system:announcement:index',
};

function canSee(principal: { permissions: string[] }, key: string): boolean {
  const required = MENU_PERMISSION[key];
  if (!required) {
    return true;
  }
  return principal.permissions.includes('*') || principal.permissions.includes(required);
}

function toMyMenuNodes(
  nodes: typeof mockMenuTree.items,
  principal: { permissions: string[] },
): MyMenuNode[] {
  return nodes
    .filter((n) => n.visible !== false)
    .filter((n) => canSee(principal, n.key))
    .map((n) => ({
      key: n.key,
      type: n.type,
      title: n.title,
      icon: n.icon ?? null,
      path: n.url && n.url.startsWith('/') ? n.url : null,
      url: n.url && !n.url.startsWith('/') ? n.url : null,
      ...(n.children ? { children: toMyMenuNodes(n.children, principal) } : {}),
    }))
    // 子项被权限过滤光的 group 不再出现。
    .filter((n) => n.type !== 'group' || (n.children?.length ?? 0) > 0);
}

export default [
  http.get(`${v1Base}/me`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    return okV1(principalToUser(auth.user));
  }),

  http.get(`${v1Base}/me/menus`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    return okV1({ menu_version: mockMenuTree.menu_version, items: toMyMenuNodes(mockMenuTree.items, auth.user) });
  }),

  http.get(`${v1Base}/org/user-options`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const keyword = (url.searchParams.get('keyword') ?? '').trim();
    const deptId = url.searchParams.get('dept_id');
    const items = mockUsers
      .filter((u) => (deptId ? u.dept_id === deptId : true))
      .filter((u) => (keyword ? (u.nickname + (u.username ?? '')).includes(keyword) : true))
      .map((u) => ({
        id: u.id,
        nickname: u.nickname,
        avatar: u.avatar,
        dept_id: u.dept_id,
        dept_name: u.dept_name,
        status: u.status,
      }));
    return okV1({ items });
  }),

  http.get(`${v1Base}/org/dept-options`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    const items = flattenDepts(mockDepts)
      .filter((d) => d.status === 'enabled')
      .map((d) => ({ id: d.id, parent_id: d.parent_id, name: d.name, sort: d.sort }));
    return okV1({ items });
  }),
] satisfies HttpHandler[];
