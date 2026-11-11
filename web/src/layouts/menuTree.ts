import type { MenuItem } from '@/api/menus';

/**
 * The `/me/menus` endpoint may return either a flat list linked by `parentId`
 * or an already-nested tree via `children`. Normalize both shapes into a
 * tree sorted by `order`.
 */
export function buildMenuTree(items: MenuItem[]): MenuItem[] {
  const byId = new Map<string, MenuItem>();
  for (const item of items) {
    byId.set(item.id, { ...item, children: item.children ? [...item.children] : undefined });
  }

  const roots: MenuItem[] = [];
  for (const node of byId.values()) {
    const parent = node.parentId ? byId.get(node.parentId) : undefined;
    if (parent) {
      parent.children = [...(parent.children ?? []), node];
    } else {
      roots.push(node);
    }
  }

  const sortRecursive = (list: MenuItem[]): MenuItem[] => {
    list.sort((a, b) => a.order - b.order);
    for (const node of list) {
      if (node.children) {
        sortRecursive(node.children);
      }
    }
    return list;
  };
  return sortRecursive(roots);
}

/**
 * Path of the currently active menu item and all its ancestors, root first.
 * Empty array when no menu item matches `pathname`.
 */
export function findMenuChain(menus: MenuItem[], pathname: string): MenuItem[] {
  for (const item of menus) {
    if (item.path && item.path === pathname) {
      return [item];
    }
    if (item.children) {
      const sub = findMenuChain(item.children, pathname);
      if (sub.length > 0) {
        return [item, ...sub];
      }
    }
  }
  return [];
}
