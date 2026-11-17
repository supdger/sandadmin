import { useMemo } from 'react';

import { Button, Skeleton, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { getShortcuts } from '@/api/v1/dashboard';
import type { MyMenuNode } from '@/api/v1/me';
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { Empty } from '@/components/Empty';
import { SvgIcon } from '@/components/SvgIcon';
import { useMyMenus } from './useMyMenus';

// 与工作台 ShortcutBar 同一查询键，缓存共享——两处数据始终一致。
const SHORTCUTS_QUERY_KEY = ['dashboard', 'shortcuts'] as const;

// /me/menus 只下发有权限的节点；收集菜单 key 与落地路径，
// 快捷入口任一项命中即算可见。
function collectMenuTargets(nodes: MyMenuNode[], out = new Set<string>()): Set<string> {
  for (const node of nodes) {
    out.add(node.key);
    if (node.path) out.add(node.path);
    if (node.url) out.add(node.url);
    if (node.children) collectMenuTargets(node.children, out);
  }
  return out;
}

export interface ShortcutGridProps {
  /** 网格列数。 */
  columns?: number;
}

/**
 * 快捷入口网格：读 GET /dashboard/shortcuts（服务端按最近访问
 * 或手工配置下发），点击按服务端 link 跳路由。只读展示，配置
 * 入口在工作台 ShortcutBar。
 *
 * 显隐按服务端下发的 /me/menus 过滤——该树已剔除无权限节点；
 * 菜单查询失败时按全不可见处理（fail-closed），不做客户端
 * is_super/权限码特判。
 */
export function ShortcutGrid({ columns = 4 }: ShortcutGridProps) {
  const { t } = useTranslation('feLayout');
  const navigate = useNavigate();
  const query = useQuery({ queryKey: SHORTCUTS_QUERY_KEY, queryFn: getShortcuts });
  const menus = useMyMenus();
  const visible = useMemo(
    () => collectMenuTargets(menus.data?.items ?? []),
    [menus.data],
  );

  if (query.isPending || menus.isPending) {
    return (
      <div
        className="grid gap-3"
        style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}
      >
        {Array.from({ length: columns }, (_, i) => (
          <Skeleton key={i} loading placeholder={<Skeleton.Paragraph rows={1} />}>
            <div />
          </Skeleton>
        ))}
      </div>
    );
  }

  if (query.isError) {
    return (
      <AdminErrorNotice
        error={query.error}
        onRetry={() => void query.refetch()}
        fallback={
          <div className="flex items-center gap-2">
            <Typography.Text type="danger" size="small">
              {t('fastEnter.loadFailed')}
            </Typography.Text>
            <Button size="small" onClick={() => void query.refetch()}>
              {t('common:actions.retry')}
            </Button>
          </div>
        }
      />
    );
  }

  const items = (query.data?.items ?? []).filter(
    (item) => visible.has(item.menu_key) || visible.has(item.link),
  );
  if (items.length === 0) {
    return <Empty title={t('fastEnter.empty')} description={undefined} />;
  }

  return (
    <div
      className="grid gap-1"
      style={{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }}
    >
      {items.map((item) => (
        <button
          key={item.menu_key}
          type="button"
          onClick={() => navigate(item.link)}
          className="flex flex-col items-center gap-1 rounded p-3 hover:bg-[var(--semi-color-fill-0)]"
          style={{ cursor: 'pointer', border: 'none', background: 'none' }}
        >
          <SvgIcon name={item.icon} size="large" />
          <Typography.Text size="small" ellipsis={{ showTooltip: true }}>
            {item.title}
          </Typography.Text>
        </button>
      ))}
    </div>
  );
}
