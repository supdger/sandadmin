import { Layout, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';

import { getMyMenus, MY_MENUS_QUERY_KEY } from '@/api/v1/me';
import { AppLogo } from '@/components/layout/AppLogo';
import { BackToTop } from '@/components/layout/BackToTop';
import { GlobalMount } from '@/components/layout/GlobalMount';
import { useGlobalUi } from '@/components/layout/globalUi';
import { HeaderBar } from '@/components/layout/HeaderBar';
import { HorizontalMenu } from '@/components/layout/HorizontalMenu';
import { MixedMenu } from '@/components/layout/MixedMenu';
import { PageContent } from '@/components/layout/PageContent';
import { SidebarMenu } from '@/components/layout/SidebarMenu';
import { useActiveTopMenu } from '@/components/layout/useActiveTopMenu';
import { WorkTabs } from '@/components/layout/WorkTabs';
import { GlobalLoading } from '@/components/GlobalLoading';
import { env } from '@/config/env';
import { useVisitReport } from '@/hooks/useVisitReport';
import { useLayoutSettings } from '@/stores/layout';
import { AnnouncementPopup } from './AnnouncementPopup';

const SIDER_WIDTH = 240;
const SIDER_COLLAPSED_WIDTH = 60;

const siderStyle = {
  width: SIDER_WIDTH,
  backgroundColor: 'var(--semi-color-bg-1)',
  borderRight: '1px solid var(--semi-color-border)',
  transition: 'width 0.2s',
} as const;

/**
 * Shell：顶栏、菜单三形态（侧边/顶部/混合）、多标签、全局件的
 * 挂载与布局。菜单树、面包屑、通知、主题等细节都在各自组件里；
 * 这里保留 /me/menus 查询供访问上报与混合布局的一级栏目判断。
 */
export function AppShell() {
  const { sidebarCollapsed: collapsed, layoutMode } = useLayoutSettings();
  const { tabsEnabled } = useGlobalUi();

  const menus = useQuery({
    queryKey: MY_MENUS_QUERY_KEY,
    queryFn: getMyMenus,
    staleTime: 60_000,
  });

  // Page-visit reporting feeds the workbench's 最近访问 stats; throttled
  // inside the hook and fully silent on failure.
  useVisitReport(menus.data?.items);

  const menuItems = menus.data?.items ?? [];
  const activeTop = useActiveTopMenu(menuItems);
  const mixChildren = layoutMode === 'mix' ? (activeTop?.children ?? []) : [];
  const showMixSider = layoutMode === 'mix' && mixChildren.length > 0;

  const header =
    layoutMode === 'side' ? (
      <HeaderBar />
    ) : (
      <HeaderBar
        collapseTrigger={false}
        hideBreadcrumb
        leading={
          <>
            <AppLogo />
            {layoutMode === 'top' ? <HorizontalMenu /> : <MixedMenu />}
          </>
        }
      />
    );

  const contentColumn = (
    <Layout style={{ backgroundColor: 'var(--semi-color-bg-0)' }}>
      {tabsEnabled ? <WorkTabs /> : null}
      <Layout.Content className="p-6">
        <PageContent />
      </Layout.Content>
      <Layout.Footer className="flex items-center justify-center py-4">
        <Typography.Text type="tertiary" size="small">
          {env.copyright}
        </Typography.Text>
      </Layout.Footer>
      <BackToTop />
    </Layout>
  );

  return (
    <Layout className="min-h-screen">
      <GlobalLoading />
      {layoutMode === 'side' ? (
        <Layout.Sider
          className="flex flex-col"
          style={{ ...siderStyle, width: collapsed ? SIDER_COLLAPSED_WIDTH : SIDER_WIDTH }}
        >
          <AppLogo collapsed={collapsed} />
          <SidebarMenu />
        </Layout.Sider>
      ) : null}

      {showMixSider ? (
        <Layout style={{ backgroundColor: 'var(--semi-color-bg-0)' }}>
          {header}
          <Layout>
            <Layout.Sider className="flex flex-col" style={siderStyle}>
              <SidebarMenu items={mixChildren} footerCollapse={false} />
            </Layout.Sider>
            {contentColumn}
          </Layout>
        </Layout>
      ) : (
        <Layout style={{ backgroundColor: 'var(--semi-color-bg-0)' }}>
          {header}
          {contentColumn}
        </Layout>
      )}

      <GlobalMount />
      <AnnouncementPopup />
    </Layout>
  );
}
