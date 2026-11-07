import { useMemo, useState, type ReactNode } from 'react';

import {
  IconApps,
  IconChevronDown,
  IconExit,
  IconHome,
  IconLayers,
  IconMoon,
  IconSetting,
  IconSun,
} from '@douyinfe/semi-icons';
import {
  Avatar,
  Breadcrumb,
  Button,
  Dropdown,
  IconButton,
  Layout,
  Nav,
  Spin,
  Typography,
} from '@douyinfe/semi-ui';
import type { NavItems, OnSelectedData } from '@douyinfe/semi-ui/lib/es/navigation';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Outlet, useLocation, useMatches, useNavigate } from 'react-router';

import { logout } from '@/api/auth';
import { getMyMenus, type MenuItem } from '@/api/menus';
import { putPreferences } from '@/api/preferences';
import { env } from '@/config/env';
import { SystemErrorNotice } from '@/components/SystemErrorNotice';
import { useMe } from '@/hooks/useMe';
import { buildMenuTree, findMenuChain } from './menuTree';
import { useThemeMode } from './themeMode';

const MENUS_QUERY_KEY = ['me', 'menus'] as const;

const SIDER_WIDTH = 240;
const SIDER_COLLAPSED_WIDTH = 60;

// Menu icons travel as names in the API payload; map them to icon components
// here so new menus only need a name, not a component reference.
const MENU_ICONS: Record<string, ReactNode> = {
  home: <IconHome />,
  setting: <IconSetting />,
};

function menuIcon(item: MenuItem, depth: number): ReactNode {
  const icon = item.icon ? MENU_ICONS[item.icon] : undefined;
  // Collapsed top-level entries only show an icon, so always render one there.
  return icon ?? (depth === 0 ? <IconApps /> : undefined);
}

function toNavItems(menus: MenuItem[], depth: number): NavItems {
  return menus.map((item) => {
    const icon = menuIcon(item, depth);
    if (item.children && item.children.length > 0) {
      // Groups are containers: keyed by id (not a navigable path).
      return {
        itemKey: item.id,
        text: item.title,
        icon,
        items: toNavItems(item.children, depth + 1),
      };
    }
    return { itemKey: item.path, text: item.title, icon };
  });
}

interface RouteHandle {
  public?: boolean;
  title?: string;
}

export function AppShell() {
  const { t } = useTranslation('shell');
  const navigate = useNavigate();
  const location = useLocation();
  const matches = useMatches();
  const me = useMe();
  const { isDark, setMode } = useThemeMode();

  const [collapsed, setCollapsed] = useState(false);
  const [manualOpenKeys, setManualOpenKeys] = useState<Array<string | number>>([]);

  const menus = useQuery({
    queryKey: MENUS_QUERY_KEY,
    queryFn: getMyMenus,
    staleTime: 60_000,
  });

  const menuTree = useMemo(() => buildMenuTree(menus.data ?? []), [menus.data]);
  const navItems = useMemo(() => toNavItems(menuTree, 0), [menuTree]);
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
        new Set([...manualOpenKeys, ...chain.slice(0, -1).map((item) => item.id)]),
      ),
    [manualOpenKeys, chain],
  );

  const onSelect = (data: OnSelectedData) => {
    const key = String(data.itemKey);
    if (key.startsWith('/')) {
      navigate(key);
    }
  };

  const onToggleTheme = () => {
    const next = isDark ? 'light' : 'dark';
    setMode(next);
    // Best-effort server-side sync; a failure must not bother the user.
    void putPreferences({ theme: next }).catch(() => undefined);
  };

  const onLogout = async () => {
    try {
      await logout();
    } finally {
      navigate('/login', { replace: true });
    }
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
          {env.appLogo ? (
            <img src={env.appLogo} alt={env.appName} className="h-7 w-7 shrink-0" />
          ) : (
            <IconLayers size="large" />
          )}
          {collapsed ? null : (
            <Typography.Title heading={6} ellipsis={{ showTooltip: true }} style={{ margin: 0 }}>
              {env.appName}
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
              <SystemErrorNotice
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
          <div className="ml-auto flex items-center gap-1">
            <IconButton
              theme="borderless"
              icon={isDark ? <IconSun /> : <IconMoon />}
              aria-label={isDark ? t('header.themeToLight') : t('header.themeToDark')}
              onClick={onToggleTheme}
            />
            {me.isPending ? (
              <Spin size="small" />
            ) : me.data ? (
              <Dropdown
                trigger="click"
                position="bottomRight"
                render={
                  <Dropdown.Menu>
                    <Dropdown.Item
                      icon={<IconSetting />}
                      onClick={() => navigate('/preferences')}
                    >
                      {t('header.preferences')}
                    </Dropdown.Item>
                    <Dropdown.Divider />
                    <Dropdown.Item type="danger" icon={<IconExit />} onClick={() => void onLogout()}>
                      {t('header.logout')}
                    </Dropdown.Item>
                  </Dropdown.Menu>
                }
              >
                <div
                  className="flex items-center gap-2 px-2 py-1"
                  style={{ cursor: 'pointer' }}
                  role="button"
                  tabIndex={0}
                >
                  <Avatar size="small" src={me.data.avatar} alt={me.data.nickname}>
                    {me.data.nickname?.slice(0, 1)}
                  </Avatar>
                  <Typography.Text>{me.data.nickname}</Typography.Text>
                  <IconChevronDown size="small" />
                </div>
              </Dropdown>
            ) : null}
          </div>
        </Layout.Header>

        <Layout.Content className="p-6">
          <Outlet />
        </Layout.Content>

        <Layout.Footer className="flex items-center justify-center py-4">
          <Typography.Text type="tertiary" size="small">
            {env.copyright}
          </Typography.Text>
        </Layout.Footer>
      </Layout>
    </Layout>
  );
}
