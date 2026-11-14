import { useEffect, useMemo, useState } from 'react';

import { IconLayers, IconMoon, IconSun } from '@douyinfe/semi-icons';
import {
  Breadcrumb,
  Button,
  IconButton,
  Layout,
  Nav,
  Spin,
  Typography,
} from '@douyinfe/semi-ui';
import type { OnSelectedData } from '@douyinfe/semi-ui/lib/es/navigation';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Outlet, useLocation, useMatches, useNavigate } from 'react-router';

import { getMyMenus, MY_MENUS_QUERY_KEY } from '@/api/v1/me';
import { GlobalSearch } from '@/components/GlobalSearch';
import { Watermark } from '@/components/Watermark';
import { env } from '@/config/env';
import { AdminErrorNotice } from '@/components/AdminErrorNotice';
import { useCan } from '@/hooks/useCan';
import { useFileImageUrl } from '@/hooks/useFileImageUrl';
import { useMe } from '@/hooks/useMe';
import { useSiteSettings, useSeedSiteSettings } from '@/hooks/useSiteSettings';
import { useVisitReport } from '@/hooks/useVisitReport';
import { AnnouncementPopup } from './AnnouncementPopup';
import { findMenuChain, toNavItems } from './menuTree';
import { NotificationBell } from './NotificationBell';
import { useThemeMode } from './themeMode';
import { UserMenu } from './UserMenu';

const SIDER_WIDTH = 240;
const SIDER_COLLAPSED_WIDTH = 60;

interface RouteHandle {
  public?: boolean;
  title?: string;
}

/**
 * Shell：侧边导航、面包屑、通知铃、主题切换、头像菜单的挂载与布局。
 * 业务逻辑（通知面板、公告弹层、改密弹窗、退出登录）都在各自组件里。
 */
export function AppShell() {
  const { t } = useTranslation('shell');
  const navigate = useNavigate();
  const location = useLocation();
  const matches = useMatches();
  const { isDark, setMode } = useThemeMode();

  // 站点设置：系统名/标志/时区/水印。有配置读权限的账号才发种子请求。
  const canConfig = useCan('system:config:index');
  useSeedSiteSettings(canConfig);
  const site = useSiteSettings();
  const me = useMe();
  const appName = site.appName || env.appName;
  // site_logo 存的是 file_id（契约）：经 /files/{id}/download 解析成可显示的地址；
  // env 配的地址类值直接透传。
  const appLogoUrl = useFileImageUrl(site.appLogo ?? env.appLogo);

  // 个人信息页才显示水印：淡淡铺当前登录人昵称，关掉设置立即消失。
  const WATERMARK_PATHS = ['/system/user', '/system/login-log', '/system/oper-log'];
  const showWatermark =
    site.watermarkEnabled !== false &&
    Boolean(me.data?.nickname) &&
    WATERMARK_PATHS.some((p) => location.pathname.startsWith(p));

  useEffect(() => {
    document.title = appName;
  }, [appName]);

  const [collapsed, setCollapsed] = useState(false);
  const [manualOpenKeys, setManualOpenKeys] = useState<Array<string | number>>([]);

  const menus = useQuery({
    queryKey: MY_MENUS_QUERY_KEY,
    queryFn: getMyMenus,
    staleTime: 60_000,
  });

  // Page-visit reporting feeds the workbench's 最近访问 stats; throttled
  // inside the hook and fully silent on failure.
  useVisitReport(menus.data?.items);

  const menuTree = useMemo(() => menus.data?.items ?? [], [menus.data]);
  const navItems = useMemo(() => toNavItems(menuTree), [menuTree]);
  const chain = useMemo(
    () => findMenuChain(menuTree, location.pathname),
    [menuTree, location.pathname],
  );
  const selectedKey = chain.length > 0 ? chain[chain.length - 1].path : '';

  // The submenu containing the active route always stays expanded; the user
  // can still open/close any other group (tracked in manualOpenKeys).
  const openKeys = useMemo(
    () =>
      Array.from(
        new Set([...manualOpenKeys, ...chain.slice(0, -1).map((item) => item.key)]),
      ),
    [manualOpenKeys, chain],
  );

  const onSelect = (data: OnSelectedData) => {
    const key = String(data.itemKey);
    if (key.startsWith('/')) {
      navigate(key);
    } else if (key.startsWith('http')) {
      window.open(key, '_blank', 'noopener');
    }
  };

  const onToggleTheme = () => {
    // Theme is a per-device display setting kept in localStorage — switching
    // it never hits the API, so read-only accounts can use it too.
    setMode(isDark ? 'light' : 'dark');
  };

  // Breadcrumb follows the menu chain; fall back to the route handle title
  // for protected pages that are not reachable from the menu.
  const lastHandle = (matches[matches.length - 1]?.handle ?? {}) as RouteHandle;
  const breadcrumbRoutes =
    chain.length > 0
      ? chain.map((item) => ({
          name: item.title,
          ...(item.path ? { path: item.path } : {}),
        }))
      : lastHandle.title
        ? [{ name: t(lastHandle.title) }]
        : [];

  return (
    <Layout className="min-h-screen">
      <Layout.Sider
        className="flex flex-col"
        style={{
          width: collapsed ? SIDER_COLLAPSED_WIDTH : SIDER_WIDTH,
          backgroundColor: 'var(--semi-color-bg-1)',
          borderRight: '1px solid var(--semi-color-border)',
          transition: 'width 0.2s',
        }}
      >
        <div className="flex h-14 shrink-0 items-center gap-2 px-4">
          {appLogoUrl ? (
            <img src={appLogoUrl} alt={appName} className="h-7 w-7 shrink-0" />
          ) : (
            <IconLayers size="large" />
          )}
          {collapsed ? null : (
            <Typography.Title heading={6} ellipsis={{ showTooltip: true }} style={{ margin: 0 }}>
              {appName}
            </Typography.Title>
          )}
        </div>
        {menus.isPending ? (
          <div className="flex flex-1 items-center justify-center">
            <Spin size="large" />
          </div>
        ) : menus.isError ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-2 px-3">
            {collapsed ? (
              // The collapsed rail is too narrow for the notice row; the
              // retry button alone keeps the failure recoverable.
              <Button size="small" onClick={() => void menus.refetch()}>
                {t('common:actions.retry')}
              </Button>
            ) : (
              <AdminErrorNotice
                error={menus.error}
                onRetry={() => void menus.refetch()}
                fallback={
                  <>
                    <Typography.Text type="danger" size="small">
                      {t('menu.loadFailed')}
                    </Typography.Text>
                    <Button size="small" onClick={() => void menus.refetch()}>
                      {t('common:actions.retry')}
                    </Button>
                  </>
                }
              />
            )}
          </div>
        ) : (
          <Nav
            style={{ flex: 1, borderRight: 'none' }}
            items={navItems}
            selectedKeys={selectedKey ? [selectedKey] : []}
            openKeys={openKeys}
            onOpenChange={(data) => setManualOpenKeys(data.openKeys ?? [])}
            onSelect={onSelect}
            isCollapsed={collapsed}
            onCollapseChange={setCollapsed}
            footer={{ collapseButton: true }}
          />
        )}
      </Layout.Sider>

      <Layout style={{ backgroundColor: 'var(--semi-color-bg-0)' }}>
        <Layout.Header
          className="flex items-center gap-3 px-4"
          style={{
            backgroundColor: 'var(--semi-color-bg-1)',
            borderBottom: '1px solid var(--semi-color-border)',
          }}
        >
          {breadcrumbRoutes.length > 0 ? (
            <Breadcrumb
              compact
              routes={breadcrumbRoutes}
              onClick={(route) => {
                if (route.path) {
                  navigate(route.path);
                }
              }}
            />
          ) : null}
          <GlobalSearch menus={menuTree} />
          <div className="ml-auto flex items-center gap-1">
            <NotificationBell />
            <IconButton
              theme="borderless"
              icon={isDark ? <IconSun /> : <IconMoon />}
              aria-label={isDark ? t('header.themeToLight') : t('header.themeToDark')}
              onClick={onToggleTheme}
            />
            <UserMenu />
          </div>
        </Layout.Header>

        <Layout.Content className="p-6" style={{ position: 'relative' }}>
          {showWatermark ? <Watermark text={me.data?.nickname ?? ''} /> : null}
          <Outlet />
        </Layout.Content>

        <Layout.Footer className="flex items-center justify-center py-4">
          <Typography.Text type="tertiary" size="small">
            {env.copyright}
          </Typography.Text>
        </Layout.Footer>

        <AnnouncementPopup />
      </Layout>
    </Layout>
  );
}
