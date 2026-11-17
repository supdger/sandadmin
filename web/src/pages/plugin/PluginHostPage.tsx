import { Suspense, useMemo } from 'react';

import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Navigate, useParams, useRoutes } from 'react-router';

import { getMyMenus, MY_MENUS_QUERY_KEY, type MyMenuNode } from '@/api/v1/me';
import { ErrorState } from '@/components/ErrorState';
import { Loading } from '@/components/Loading';
import { hasPermission } from '@/hooks/useCan';
import { useMe } from '@/hooks/useMe';
import { getPluginModule } from '@/plugins/registry';

// 菜单树里出现 `plugin:<code>` 前缀节点（或节点带 plugin=<code> 字段）
// 视为插件已安装；后端卸载插件时整棵子树从 /me/menus 撤下。
function hasPluginMenu(items: MyMenuNode[], code: string): boolean {
  const prefix = `plugin:${code}`;
  return items.some(
    (item) =>
      item.plugin === code ||
      item.key === prefix ||
      item.key.startsWith(`${prefix}:`) ||
      hasPluginMenu(item.children ?? [], code),
  );
}

/**
 * 插件路由宿主：`/plugin/<code>/*` 统一落到这里。
 * 权限先于安装判定——插件菜单按权限下发，无权限者菜单树里看不到节点，
 * 若先查安装会把「无权」误判成「未安装」。
 */
export function PluginHostPage() {
  const { t } = useTranslation('pluginHost');
  const { pluginCode = '' } = useParams();
  const plugin = getPluginModule(pluginCode);

  // 与 AppShell 同一查询键：正常点击菜单进来时数据已在缓存里。
  const menus = useQuery({
    queryKey: MY_MENUS_QUERY_KEY,
    queryFn: getMyMenus,
    staleTime: 60_000,
  });
  const me = useMe();

  const isInstalled = useMemo(
    () => hasPluginMenu(menus.data?.items ?? [], pluginCode),
    [menus.data, pluginCode],
  );
  const outlet = useRoutes(plugin?.routes ?? []);

  if (!plugin) {
    return (
      <ErrorState
        kind="404"
        title={t('notFound.title')}
        description={t('notFound.description')}
      />
    );
  }

  if (me.isPending || menus.isPending) {
    return <Loading />;
  }

  if (menus.isError) {
    return <ErrorState kind="error" error={menus.error} onRetry={() => void menus.refetch()} />;
  }

  if (plugin.permission && !hasPermission(me.data?.permissions ?? [], plugin.permission)) {
    return <Navigate to="/403" replace />;
  }

  if (!isInstalled) {
    return (
      <ErrorState
        kind="404"
        title={t('notInstalled.title')}
        description={t('notInstalled.description')}
      />
    );
  }

  return (
    <Suspense fallback={<Loading />}>
      {outlet ?? (
        <ErrorState
          kind="404"
          title={t('notFound.title')}
          description={t('notFound.description')}
        />
      )}
    </Suspense>
  );
}
