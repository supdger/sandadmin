import { useLocation } from 'react-router';

import type { MyMenuNode } from '@/api/v1/me';
import { useGlobalUi } from './globalUi';
import { topNodeForPath } from './menuPath';

/**
 * 混合布局的当前一级栏目：优先按路由命中，其次用用户最后一次
 * 点选的一级栏目（路由不在菜单树时的兜底），再退化为第一个节点。
 */
export function useActiveTopMenu(menus: MyMenuNode[]): MyMenuNode | null {
  const { pathname } = useLocation();
  const { activeTopKey } = useGlobalUi();

  const byPath = topNodeForPath(menus, pathname);
  if (byPath) {
    return byPath;
  }
  return menus.find((item) => item.key === activeTopKey) ?? menus[0] ?? null;
}
