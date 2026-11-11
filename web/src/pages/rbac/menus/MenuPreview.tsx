import { useMemo, type ReactNode } from 'react';

import { Nav, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import type { MenuNode } from '@/api/v1/menus';
import { menuIconNode } from '@/pages/rbac/lib/menuIcons';

interface NavItem {
  itemKey: string;
  text: string;
  icon?: ReactNode;
  items?: NavItem[];
}

function toNavItems(nodes: MenuNode[]): NavItem[] {
  const items: NavItem[] = [];
  for (const node of nodes) {
    if (!node.visible) {
      continue;
    }
    if (node.type === 'group') {
      const children = toNavItems(node.children ?? []);
      // 空分组渲染出来是个死节点——整组隐藏。
      if (children.length === 0) {
        continue;
      }
      items.push({
        itemKey: node.key,
        text: node.title,
        icon: menuIconNode(node.icon),
        items: children,
      });
      continue;
    }
    items.push({
      itemKey: node.key,
      text: node.title,
      icon: menuIconNode(node.icon),
    });
  }
  return items;
}

export interface MenuPreviewProps {
  items: MenuNode[];
}

/**
 * 宽屏下与编辑器并排的只读 240px 侧边栏预览。
 * 还原同事看到的样子：只显示可见节点，分组可折叠。
 */
export function MenuPreview({ items }: MenuPreviewProps) {
  const { t } = useTranslation('rbac');
  const navItems = useMemo(() => toNavItems(items), [items]);

  return (
    <aside className="hidden w-60 shrink-0 flex-col gap-2 min-[1440px]:flex">
      <Typography.Text type="secondary" size="small">
        {t('menus.previewTitle')}
      </Typography.Text>
      <div className="rounded-lg border border-[var(--semi-color-border)] bg-[var(--semi-color-bg-1)] p-2">
        <Nav
          mode="vertical"
          items={navItems}
          style={{ border: 'none' }}
          selectedKeys={[]}
        />
      </div>
    </aside>
  );
}
