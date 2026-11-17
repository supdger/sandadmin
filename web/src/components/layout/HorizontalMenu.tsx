import { useMemo } from 'react';

import { Button, Nav, Spin } from '@douyinfe/semi-ui';
import type { OnSelectedData } from '@douyinfe/semi-ui/lib/es/navigation';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router';

import type { MyMenuNode } from '@/api/v1/me';
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { findMenuChain, toNavItems } from '@/layouts/menuTree';
import { useMyMenus } from './useMyMenus';

export interface HorizontalMenuProps {
  /** 直接给菜单树；缺省自取 /me/menus。 */
  items?: MyMenuNode[];
}

/**
 * 顶部横排菜单。与侧边菜单同一份 /me/menus 数据；多级节点
 * 由 Semi Nav 横向模式自带下拉呈现，选中态跟路由。
 */
export function HorizontalMenu({ items }: HorizontalMenuProps) {
  const { t } = useTranslation('feLayout');
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const query = useMyMenus();

  const menus = useMemo(() => items ?? query.data?.items ?? [], [items, query.data]);
  const navItems = useMemo(() => toNavItems(menus), [menus]);
  const chain = useMemo(() => findMenuChain(menus, pathname), [menus, pathname]);
  const selectedKey = chain.length > 0 ? (chain[chain.length - 1].path ?? '') : '';

  const handleSelect = (data: OnSelectedData) => {
    const key = String(data.itemKey);
    if (key.startsWith('/')) {
      navigate(key);
    } else if (key.startsWith('http')) {
      window.open(key, '_blank', 'noopener');
    }
  };

  if (items === undefined) {
    if (query.isPending) {
      return <Spin size="small" />;
    }
    if (query.isError) {
      return (
        <AdminErrorNotice
          error={query.error}
          onRetry={() => void query.refetch()}
          fallback={
            <div className="flex items-center gap-2">
              <span className="text-[var(--semi-color-danger)] text-sm">
                {t('menu.loadFailed')}
              </span>
              <Button size="small" onClick={() => void query.refetch()}>
                {t('common:actions.retry')}
              </Button>
            </div>
          }
        />
      );
    }
  }

  return (
    <Nav
      mode="horizontal"
      style={{ flex: 1, borderBottom: 'none', minWidth: 0 }}
      items={navItems}
      selectedKeys={selectedKey ? [selectedKey] : []}
      onSelect={handleSelect}
    />
  );
}
