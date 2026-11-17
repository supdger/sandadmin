import { useMemo } from 'react';

import { Breadcrumb } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { useLocation, useMatches, useNavigate } from 'react-router';

import i18n from '@/i18n/i18n';
import { findMenuChain, resolveMenuTitle } from '@/layouts/menuTree';
import { PATH_HOME } from '@/routes/paths';
import { useMyMenus } from './useMyMenus';

interface RouteHandle {
  title?: string;
}

interface Crumb {
  name: string;
  path?: string;
}

/**
 * 面包屑：以 /me/menus 菜单链为主，路由 handle.title（shell 命名
 * 空间的 key）兜底，最前面固定放首页。首页和末级只是定位标识，
 * 不可点击。
 */
export function RouteBreadcrumb() {
  const { t } = useTranslation('feLayout');
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const matches = useMatches();
  const menus = useMyMenus();

  const crumbs = useMemo<Crumb[]>(() => {
    const home: Crumb = { name: t('breadcrumb.home') };
    const chain = findMenuChain(menus.data?.items ?? [], pathname);
    if (chain.length > 0) {
      if (chain.length === 1 && chain[0].path === PATH_HOME) {
        return [home];
      }
      // 链中指向首页的祖先与固定首页项重复，剔除；末级不带 path 即不可点。
      const middle = chain.slice(0, -1).flatMap((item) =>
        item.path && item.path !== PATH_HOME
          ? [{ name: resolveMenuTitle(item), path: item.path }]
          : [],
      );
      return [home, ...middle, { name: resolveMenuTitle(chain[chain.length - 1]) }];
    }
    const handle = (matches[matches.length - 1]?.handle ?? {}) as RouteHandle;
    return handle.title
      ? [home, { name: i18n.t(handle.title, { ns: 'shell' }) }]
      : [home];
  }, [menus.data, pathname, matches, t]);

  return (
    <Breadcrumb
      compact
      routes={crumbs}
      onClick={(route) => {
        if (route.path) {
          navigate(route.path);
        }
      }}
    />
  );
}
