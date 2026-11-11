import type { HttpHandler } from 'msw';
import { http } from 'msw';

import type { MenuLinkBody, MenuMoveBody, MenuNode, MenuPatchBody, MenuVisibleBody } from '@/api/v1/menus';
import { failV1, okV1, queryParam, requirePermission, v1Base } from './_v1';
import { bumpAuthzVersion, mockMenuTree, restoreMenuTree } from './_v1data';

const MENU_PERMS = {
  index: 'system:menu:index',
  create: 'system:menu:create',
  update: 'system:menu:update',
  remove: 'system:menu:delete',
  reset: 'system:menu:reset',
} as const;

function findNode(nodes: MenuNode[], key: string): MenuNode | undefined {
  for (const node of nodes) {
    if (node.key === key) return node;
    const hit = node.children ? findNode(node.children, key) : undefined;
    if (hit) return hit;
  }
  return undefined;
}

function parentOf(nodes: MenuNode[], key: string, parent: MenuNode | null = null): MenuNode | null | undefined {
  for (const node of nodes) {
    if (node.key === key) return parent;
    if (node.children) {
      const hit = parentOf(node.children, key, node);
      if (hit !== undefined) return hit;
    }
  }
  return undefined;
}

function removeNode(nodes: MenuNode[], key: string): boolean {
  const idx = nodes.findIndex((n) => n.key === key);
  if (idx >= 0) {
    nodes.splice(idx, 1);
    return true;
  }
  return nodes.some((n) => (n.children ? removeNode(n.children, key) : false));
}

function filterTree(nodes: MenuNode[], keyword: string, source: string): MenuNode[] {
  return nodes
    .map((n) => {
      const children = n.children ? filterTree(n.children, keyword, source) : undefined;
      const selfMatch =
        (!keyword || n.title.includes(keyword) || n.key.includes(keyword)) &&
        (!source || n.source?.kind === source);
      if (keyword && !selfMatch && !children?.length) return null;
      if (source && !selfMatch && !children?.length) return null;
      return { ...n, ...(children ? { children } : {}) };
    })
    .filter((n): n is MenuNode => n !== null);
}

function staleCheck(baseVersion: number | undefined): Response | null {
  if (baseVersion !== undefined && baseVersion !== mockMenuTree.menu_version) {
    return failV1(409, 'STALE_VERSION', '菜单已被他人修改，请刷新后重试', {
      base_version: ['菜单已被他人修改，请刷新后重试'],
    });
  }
  return null;
}

let menuSeq = 0;
function nextMenuKey(prefix: string): string {
  menuSeq += 1;
  return `custom:${prefix}-${Date.now().toString(36)}-${menuSeq}`;
}

export default [
  http.get(`${v1Base}/menus/tree`, ({ request }) => {
    const auth = requirePermission(request, MENU_PERMS.index);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const keyword = queryParam(url, 'keyword');
    const source = queryParam(url, 'source');
    if (keyword === '__error__') {
      return failV1(500, 'INTERNAL_ERROR', '菜单加载失败，请稍后再试');
    }
    return okV1({
      menu_version: mockMenuTree.menu_version,
      items: filterTree(mockMenuTree.items, keyword, source),
    });
  }),

  http.patch(`${v1Base}/menus/:menuKey`, async ({ request, params }) => {
    const auth = requirePermission(request, MENU_PERMS.update);
    if ('response' in auth) return auth.response;
    const node = findNode(mockMenuTree.items, String(params.menuKey));
    if (!node) {
      return failV1(404, 'MENU_NOT_FOUND', '要操作的菜单不存在或已被删除');
    }
    const body = (await request.json()) as MenuPatchBody;
    if (body.title !== undefined) {
      if (!body.title.trim()) {
        return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
          title: ['菜单标题不能为空'],
        });
      }
      node.title = body.title.trim();
      node.original_title = node.original_title ?? node.title;
      node.rename_suggested = false;
    }
    if (body.icon !== undefined) node.icon = body.icon;
    if (body.visible !== undefined && body.visible !== null) node.visible = body.visible;
    mockMenuTree.menu_version += 1;
    bumpAuthzVersion();
    return okV1({});
  }),

  http.delete(`${v1Base}/menus/:menuKey`, ({ request, params }) => {
    const auth = requirePermission(request, MENU_PERMS.remove);
    if ('response' in auth) return auth.response;
    const key = String(params.menuKey);
    const node = findNode(mockMenuTree.items, key);
    if (!node) {
      return failV1(404, 'MENU_NOT_FOUND', '要操作的菜单不存在或已被删除');
    }
    if (node.source?.kind !== 'custom') {
      return failV1(409, 'MENU_LOCKED', '系统与插件菜单不能删除，只能隐藏');
    }
    removeNode(mockMenuTree.items, key);
    mockMenuTree.menu_version += 1;
    bumpAuthzVersion();
    return okV1({});
  }),

  http.post(`${v1Base}/menus/:menuKey/move`, async ({ request, params }) => {
    const auth = requirePermission(request, MENU_PERMS.update);
    if ('response' in auth) return auth.response;
    const key = String(params.menuKey);
    const node = findNode(mockMenuTree.items, key);
    if (!node) {
      return failV1(404, 'MENU_NOT_FOUND', '要操作的菜单不存在或已被删除');
    }
    const body = (await request.json()) as MenuMoveBody;
    const stale = staleCheck(body.base_version);
    if (stale) return stale;
    const parent = parentOf(mockMenuTree.items, key);
    if (parent === undefined) {
      return failV1(404, 'MENU_NOT_FOUND', '要操作的菜单不存在或已被删除');
    }
    // 深度兜底：分组最多嵌套 2 层。
    if (body.parent_key && findNode(mockMenuTree.items, body.parent_key)?.type !== 'group') {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        parent_key: ['父级必须是分组'],
      });
    }
    const siblings = body.parent_key
      ? (findNode(mockMenuTree.items, body.parent_key)?.children ?? [])
      : mockMenuTree.items;
    if (parent) removeNode(parent.children ?? [], key);
    else removeNode(mockMenuTree.items, key);
    const index = Math.max(0, Math.min(body.index ?? siblings.length, siblings.length));
    siblings.splice(index, 0, node);
    mockMenuTree.menu_version += 1;
    bumpAuthzVersion();
    return okV1({});
  }),

  http.put(`${v1Base}/menus/:menuKey/visible`, async ({ request, params }) => {
    const auth = requirePermission(request, MENU_PERMS.update);
    if ('response' in auth) return auth.response;
    const node = findNode(mockMenuTree.items, String(params.menuKey));
    if (!node) {
      return failV1(404, 'MENU_NOT_FOUND', '要操作的菜单不存在或已被删除');
    }
    if (node.locked?.hide) {
      return failV1(409, 'MENU_LOCKED', '该菜单不允许隐藏');
    }
    const body = (await request.json()) as MenuVisibleBody;
    const stale = staleCheck(body.base_version);
    if (stale) return stale;
    node.visible = body.visible;
    mockMenuTree.menu_version += 1;
    bumpAuthzVersion();
    return okV1({});
  }),

  http.post(`${v1Base}/menus/groups`, async ({ request }) => {
    const auth = requirePermission(request, MENU_PERMS.create);
    if ('response' in auth) return auth.response;
    const body = (await request.json()) as { title?: string; icon?: string | null };
    if (!body.title?.trim()) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        title: ['分组名称不能为空'],
      });
    }
    const key = nextMenuKey('group');
    const node: MenuNode = {
      key,
      type: 'group',
      title: body.title.trim(),
      icon: body.icon ?? null,
      source: { kind: 'custom' },
      visible: true,
      locked: { move: false, hide: false },
      children: [],
    };
    mockMenuTree.items.push(node);
    mockMenuTree.menu_version += 1;
    bumpAuthzVersion();
    return okV1({ key }, { status: 201 });
  }),

  http.post(`${v1Base}/menus/links`, async ({ request }) => {
    const auth = requirePermission(request, MENU_PERMS.create);
    if ('response' in auth) return auth.response;
    const body = (await request.json()) as MenuLinkBody;
    if (!body.title?.trim() || !body.url?.trim()) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        title: ['标题和链接不能为空'],
      });
    }
    if (!/^https?:\/\//.test(body.url.trim()) && !body.url.trim().startsWith('/')) {
      return failV1(400, 'MENU_URL_INVALID', '链接地址不合法，请填写 http(s) 或站内路径');
    }
    const key = nextMenuKey('link');
    const node: MenuNode = {
      key,
      type: 'link',
      title: body.title.trim(),
      icon: body.icon ?? null,
      source: { kind: 'custom' },
      visible: true,
      locked: { move: false, hide: false },
      url: body.url.trim(),
    };
    const parent = body.parent_key ? findNode(mockMenuTree.items, body.parent_key) : undefined;
    if (body.parent_key && (!parent || parent.type !== 'group')) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        parent_key: ['父级必须是分组'],
      });
    }
    if (parent?.children) parent.children.push(node);
    else mockMenuTree.items.push(node);
    mockMenuTree.menu_version += 1;
    bumpAuthzVersion();
    return okV1({ key }, { status: 201 });
  }),

  http.post(`${v1Base}/menus/reset`, ({ request }) => {
    const auth = requirePermission(request, MENU_PERMS.reset);
    if ('response' in auth) return auth.response;
    restoreMenuTree();
    bumpAuthzVersion();
    return okV1({ menu_version: mockMenuTree.menu_version });
  }),
] satisfies HttpHandler[];
