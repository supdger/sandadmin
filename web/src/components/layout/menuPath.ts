import type { MyMenuNode } from '@/api/v1/me';
import { findMenuChain } from '@/layouts/menuTree';

/**
 * 菜单树路径辅助。混合布局要回答两个问题：当前路由属于哪个
 * 一级栏目（侧栏跟着谁走），以及点了一个一级栏目该去哪
 * （容器节点本身没有页面）。
 */

/** 当前路由命中的一级菜单节点；未命中为 null。 */
export function topNodeForPath(menus: MyMenuNode[], pathname: string): MyMenuNode | null {
  const chain = findMenuChain(menus, pathname);
  return chain.length > 0 ? chain[0] : null;
}

/** 节点向下找到的第一个站内页 path；纯容器/外链节点返回 null。 */
export function firstLeafPath(node: MyMenuNode): string | null {
  if (node.type === 'page' && node.path) {
    return node.path;
  }
  for (const child of node.children ?? []) {
    const path = firstLeafPath(child);
    if (path) {
      return path;
    }
  }
  return null;
}
