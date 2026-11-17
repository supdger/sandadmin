import { useMemo, useState } from 'react';

import { Button, Nav, Spin } from '@douyinfe/semi-ui';
import type { OnSelectedData } from '@douyinfe/semi-ui/lib/es/navigation';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router';

import type { MyMenuNode } from '@/api/v1/me';
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { findMenuChain, toNavItems } from '@/layouts/menuTree';
import { setSidebarCollapsed, useLayoutSettings } from '@/stores/layout';
import { useMyMenus } from './useMyMenus';

export interface SidebarMenuProps {
  /**
   * 直接给菜单树（混合布局的侧栏只传一级栏目的 children）；
   * 缺省自取 /me/menus，含加载与失败态。
   */
  items?: MyMenuNode[];
  /** 折叠态；缺省读布局设置。 */
  collapsed?: boolean;
  /** 底部折叠按钮，默认开；混合布局的侧栏一般关掉。 */
  footerCollapse?: boolean;
}

/**
 * 侧边竖排菜单。数据源是服务端已按权限过滤的 /me/menus 树，
 * 前端不再二次过滤；选中态与展开态跟当前路由走。
 */
export function SidebarMenu({ items, collapsed, footerCollapse = true }: SidebarMenuProps) {
  const { t } = useTranslation('feLayout');
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const settings = useLayoutSettings();
  const query = useMyMenus();
  const [manualOpenKeys, setManualOpenKeys] = useState<Array<string | number>>([]);

  const isCollapsed = collapsed ?? settings.sidebarCollapsed;
  const menus = useMemo(() => items ?? query.data?.items ?? [], [items, query.data]);

  const navItems = useMemo(() => toNavItems(menus), [menus]);
  const chain = useMemo(() => findMenuChain(menus, pathname), [menus, pathname]);
  const selectedKey = chain.length > 0 ? (chain[chain.length - 1].path ?? '') : '';

  // 含激活路由的分组始终展开；其余分组的展开/收起由用户控制。
  const openKeys = useMemo(
    () =>
      Array.from(
        new Set([...manualOpenKeys, ...chain.slice(0, -1).map((item) => item.key)]),
      ),
    [manualOpenKeys, chain],
  );

  const handleSelect = (data: OnSelectedData) => {
    const key = String(data.itemKey);
    if (key.startsWith('/')) {
      navigate(key);
    } else if (key.startsWith('http')) {
      window.open(key, '_blank', 'noopener');
    }
  };

  // items 由外部传入时（混合布局侧栏）查询态归调用方管，这里直接渲染。
  if (items === undefined) {
    if (query.isPending) {
      return (
        <div className="flex flex-1 items-center justify-center">
          <Spin size="large" />
        </div>
      );
    }
    if (query.isError) {
      return (
        <div className="flex flex-1 flex-col items-center justify-center gap-2 px-3">
          {isCollapsed ? (
            // 折叠栏太窄放不下整行提示，只留重试入口保证可恢复。
            <Button size="small" onClick={() => void query.refetch()}>
              {t('common:actions.retry')}
            </Button>
          ) : (
            <AdminErrorNotice
              error={query.error}
              onRetry={() => void query.refetch()}
              fallback={
                <>
                  <span className="text-[var(--semi-color-danger)] text-sm">
                    {t('menu.loadFailed')}
                  </span>
                  <Button size="small" onClick={() => void query.refetch()}>
                    {t('common:actions.retry')}
                  </Button>
                </>
              }
            />
          )}
        </div>
      );
    }
  }

  return (
    <Nav
      style={{ flex: 1, borderRight: 'none' }}
      items={navItems}
      selectedKeys={selectedKey ? [selectedKey] : []}
      openKeys={openKeys}
      onOpenChange={(data) => setManualOpenKeys(data.openKeys ?? [])}
      onSelect={handleSelect}
      isCollapsed={isCollapsed}
      onCollapseChange={setSidebarCollapsed}
      footer={footerCollapse ? { collapseButton: true } : undefined}
    />
  );
}
