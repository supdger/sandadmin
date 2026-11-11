import { useEffect, useMemo, useState, type ReactNode } from 'react';

import {
  IconApps,
  IconBell,
  IconChevronDown,
  IconExit,
  IconHome,
  IconLayers,
  IconLock,
  IconMoon,
  IconSetting,
  IconSun,
  IconUser,
} from '@douyinfe/semi-icons';
import {
  Avatar,
  Badge,
  Breadcrumb,
  Button,
  Dropdown,
  IconButton,
  Layout,
  Nav,
  Popover,
  Skeleton,
  Spin,
  Toast,
  Tooltip,
  Typography,
} from '@douyinfe/semi-ui';
import type { NavItems, OnSelectedData } from '@douyinfe/semi-ui/lib/es/navigation';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Outlet, useLocation, useMatches, useNavigate } from 'react-router';

import { logout } from '@/api/auth';
import { getMyMenus, type MenuItem } from '@/api/menus';
import {
  getAnnouncementPopup,
  getNotifications,
  getUnreadCount,
  markAllNotificationsRead,
  markInboxItemRead,
  type NotificationItem,
} from '@/api/notifications';
import { env } from '@/config/env';
import { Empty } from '@/components/Empty';
import { ImportantAnnouncementModal } from '@/components/ImportantAnnouncementModal';
import { PasswordChangeModal } from '@/components/PasswordChangeModal';
import { SystemErrorNotice } from '@/components/SystemErrorNotice';
import { useMe } from '@/hooks/useMe';
import { useVisitReport } from '@/hooks/useVisitReport';
import { apiErrorMessage } from '@/lib/apiClient';
import { formatRelativeTime } from '@/pages/personal/relativeTime';
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

const NOTIFICATIONS_QUERY_KEY = ['notifications'] as const;
const UNREAD_COUNT_KEY = ['notifications', 'unread-count'] as const;
const PANEL_KEY = ['notifications', 'panel'] as const;
const POPUP_KEY = ['notifications', 'announcements-popup'] as const;

/**
 * Header bell: badge polls the unread count every 60s (paused while the tab
 * is hidden, refetched on return); the popover lists the latest six inbox
 * items and marks them read through the endpoint matching each kind.
 */
function NotificationBell() {
  const { t } = useTranslation('notifications');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);

  const countQuery = useQuery({
    queryKey: UNREAD_COUNT_KEY,
    queryFn: getUnreadCount,
    refetchInterval: 60_000,
    retry: 1,
  });
  const { refetch: refetchCount } = countQuery;

  useEffect(() => {
    const onVisible = () => {
      if (document.visibilityState === 'visible') {
        void refetchCount();
      }
    };
    document.addEventListener('visibilitychange', onVisible);
    return () => document.removeEventListener('visibilitychange', onVisible);
  }, [refetchCount]);

  const panelQuery = useQuery({
    queryKey: PANEL_KEY,
    queryFn: () => getNotifications({ page: 1, pageSize: 6 }),
    enabled: open,
  });

  const invalidateAll = () =>
    void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_QUERY_KEY });

  const handleOpenItem = async (item: NotificationItem) => {
    setOpen(false);
    if (!item.read_time) {
      try {
        await markInboxItemRead(item);
        invalidateAll();
      } catch {
        // Best-effort: the item still navigates even if marking fails.
      }
    }
    if (item.link) {
      navigate(item.link);
    }
  };

  const handleMarkAll = async () => {
    try {
      await markAllNotificationsRead();
      invalidateAll();
    } catch (e) {
      Toast.error(apiErrorMessage(e, t('panel.loadFailed')));
    }
  };

  const count = countQuery.data?.count ?? 0;
  const items = panelQuery.data?.items ?? [];

  return (
    <Popover
      visible={open}
      onVisibleChange={setOpen}
      trigger="click"
      position="bottomRight"
      content={
        <div className="w-[360px]">
          <div className="flex items-center justify-between border-b px-3 py-2" style={{ borderColor: 'var(--semi-color-border)' }}>
            <Typography.Text strong>{t('panel.title')}</Typography.Text>
            <Button size="small" theme="borderless" onClick={() => void handleMarkAll()}>
              {t('panel.markAll')}
            </Button>
          </div>
          <div className="max-h-[420px] overflow-y-auto">
            {panelQuery.isPending ? (
              <div className="flex flex-col gap-3 p-3">
                {[0, 1, 2].map((i) => (
                  <Skeleton key={i} loading placeholder={<Skeleton.Paragraph rows={1} />}>
                    <div />
                  </Skeleton>
                ))}
              </div>
            ) : panelQuery.isError ? (
              <div className="flex flex-col items-center gap-2 p-6">
                <Typography.Text type="danger" size="small">
                  {t('panel.loadFailed')}
                </Typography.Text>
                <Button size="small" onClick={() => void panelQuery.refetch()}>
                  {t('common:actions.retry')}
                </Button>
              </div>
            ) : items.length === 0 ? (
              <Empty title={t('panel.empty')} description={t('panel.emptyDesc')} />
            ) : (
              <div className="flex flex-col">
                {items.map((item) => {
                  const unread = !item.read_time;
                  return (
                    <button
                      key={`${item.kind}-${item.id}`}
                      type="button"
                      onClick={() => void handleOpenItem(item)}
                      className="flex w-full items-start gap-2 px-3 py-2 text-left hover:bg-[var(--semi-color-fill-0)]"
                    >
                      <span
                        className="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full"
                        style={{
                          backgroundColor: unread ? 'var(--semi-color-primary)' : 'transparent',
                        }}
                        aria-hidden
                      />
                      <div className="min-w-0 flex-1">
                        <Typography.Text strong={unread} size="small" ellipsis={{ showTooltip: true }}>
                          {item.kind === 'announcement' ? (item.title ?? item.text) : item.text}
                        </Typography.Text>
                        <div>
                          <Typography.Text type="tertiary" size="small">
                            {formatRelativeTime(t, item.create_time)}
                          </Typography.Text>
                        </div>
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </div>
          <div className="border-t px-3 py-2 text-center" style={{ borderColor: 'var(--semi-color-border)' }}>
            <Button size="small" theme="borderless" onClick={() => { setOpen(false); navigate('/notifications'); }}>
              {t('panel.viewAll')}
            </Button>
          </div>
        </div>
      }
    >
      <span className="inline-flex">
        <Badge count={count} overflowCount={99} type="danger">
          <Tooltip content={t('bell.tooltip')} position="bottom">
            <IconButton icon={<IconBell />} aria-label={t('bell.tooltip')} />
          </Tooltip>
        </Badge>
      </span>
    </Popover>
  );
}

/** Fetches important unread announcements once per session and pops them in order. */
function AnnouncementPopup() {
  const queryClient = useQueryClient();
  const popup = useQuery({
    queryKey: POPUP_KEY,
    queryFn: getAnnouncementPopup,
    staleTime: 60_000,
    retry: 1,
  });
  const [closed, setClosed] = useState(false);

  const items = popup.data?.items ?? [];
  if (closed || items.length === 0) {
    return null;
  }
  return (
    <ImportantAnnouncementModal
      items={items}
      onCloseAll={() => {
        setClosed(true);
        void queryClient.invalidateQueries({ queryKey: POPUP_KEY });
      }}
    />
  );
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
  const [passwordModalOpen, setPasswordModalOpen] = useState(false);

  const menus = useQuery({
    queryKey: MENUS_QUERY_KEY,
    queryFn: getMyMenus,
    staleTime: 60_000,
  });

  // Page-visit reporting feeds the workbench's 最近访问 stats; throttled
  // inside the hook and fully silent on failure.
  useVisitReport(menus.data);

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
    // Theme is a per-device display setting kept in localStorage — switching
    // it never hits the API, so read-only accounts can use it too.
    setMode(isDark ? 'light' : 'dark');
  };

  const onLogout = async () => {
    try {
      await logout();
    } catch (e) {
      // The token is cleared locally either way; still surface the backend's
      // reason instead of failing silently.
      Toast.error(apiErrorMessage(e, t('common:errors.system.message')));
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
            <NotificationBell />
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
                      icon={<IconUser />}
                      onClick={() => navigate('/profile')}
                    >
                      {t('profile:title')}
                    </Dropdown.Item>
                    <Dropdown.Item
                      icon={<IconLock />}
                      onClick={() => setPasswordModalOpen(true)}
                    >
                      {t('profile:security.changePassword')}
                    </Dropdown.Item>
                    <Dropdown.Divider />
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

        <AnnouncementPopup />
        <PasswordChangeModal
          visible={passwordModalOpen}
          onClose={() => setPasswordModalOpen(false)}
        />
      </Layout>
    </Layout>
  );
}
