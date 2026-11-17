import { useEffect } from 'react';

import { useLocation, useMatches } from 'react-router';

import i18n from '@/i18n/i18n';
import { findMenuChain, resolveMenuTitle } from '@/layouts/menuTree';
import { openTab } from '@/stores/tabs';
import { useMyMenus } from './useMyMenus';

interface RouteHandle {
  title?: string;
}

/**
 * 路由 → 多标签的登记器：pathname 变化时把当前页面记进 tabs store。
 * 标题取菜单节点 → handle.title → 路径文本；key 用 pathname，
 * 同一路径不同查询参数共享一个标签。
 */
export function useTabRouteSync(enabled: boolean): void {
  const location = useLocation();
  const matches = useMatches();
  const menus = useMyMenus();

  const pathname = location.pathname;
  const path = pathname + location.search;
  const items = menus.data?.items;
  const handleTitle =
    (matches[matches.length - 1]?.handle as RouteHandle | undefined)?.title ?? null;

  useEffect(() => {
    if (!enabled || pathname === '') {
      return;
    }
    const chain = findMenuChain(items ?? [], pathname);
    const leaf = chain[chain.length - 1];
    const title = leaf
      ? resolveMenuTitle(leaf)
      : handleTitle
        ? i18n.t(handleTitle, { ns: 'shell' })
        : pathname;
    openTab({ key: pathname, path, title });
  }, [enabled, pathname, path, items, handleTitle]);
}
