import { useMemo } from 'react';

import { Button, Nav, Spin } from '@douyinfe/semi-ui';
import type { OnSelectedData } from '@douyinfe/semi-ui/lib/es/navigation';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import type { MyMenuNode } from '@/api/v1/me';
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { SvgIcon } from '@/components/SvgIcon';
import { resolveMenuTitle } from '@/layouts/menuTree';
import { setActiveTopKey } from './globalUi';
import { firstLeafPath } from './menuPath';
import { useActiveTopMenu } from './useActiveTopMenu';
import { useMyMenus } from './useMyMenus';

export interface MixedMenuProps {
  /** 直接给菜单树；缺省自取 /me/menus。 */
  items?: MyMenuNode[];
}

/**
 * 混合布局的一级横排菜单：只渲染顶层节点（下级由侧栏
 * SidebarMenu 承接）。点容器节点时激活该栏目并跳到它的
 * 第一个站内页；侧栏数据源用 useActiveTopMenu 取同一栏目。
 */
export function MixedMenu({ items }: MixedMenuProps) {
  const { t } = useTranslation('feLayout');
  const navigate = useNavigate();
  const query = useMyMenus();

  const menus = useMemo(() => items ?? query.data?.items ?? [], [items, query.data]);
  const activeTop = useActiveTopMenu(menus);

  const navItems = useMemo(
    () =>
      menus.map((node) => ({
        itemKey: node.key,
        text: resolveMenuTitle(node),
        icon: <SvgIcon name={node.icon} />,
      })),
    [menus],
  );

  const handleSelect = (data: OnSelectedData) => {
    const node = menus.find((item) => item.key === String(data.itemKey));
    if (!node) {
      return;
    }
    if (node.type === 'link' && node.url) {
      window.open(node.url, '_blank', 'noopener');
      return;
    }
    setActiveTopKey(node.key);
    // 页面节点直接跳，容器节点落到它的第一个叶子页。
    const path = node.type === 'page' && node.path ? node.path : firstLeafPath(node);
    if (path) {
      navigate(path);
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
      selectedKeys={activeTop ? [activeTop.key] : []}
      onSelect={handleSelect}
    />
  );
}
