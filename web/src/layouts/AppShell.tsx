/**
 * Minimal placeholder shell for the foundation milestone.
 * NOTE: the shell workstream will fully replace this file with the real
 * layout (sidebar navigation, top bar, breadcrumbs, theme switch, user menu).
 */

import { Layout, Typography } from '@douyinfe/semi-ui';
import { Outlet } from 'react-router';

import { env } from '@/config/env';

export function AppShell() {
  return (
    <Layout className="min-h-screen">
      <Layout.Header className="flex items-center gap-2 px-4">
        {env.appLogo ? (
          <img src={env.appLogo} alt={env.appName} className="h-7" />
        ) : null}
        <Typography.Title heading={5} style={{ margin: 0 }}>
          {env.appName}
        </Typography.Title>
      </Layout.Header>
      <Layout.Content className="p-4">
        <Outlet />
      </Layout.Content>
      <Layout.Footer className="flex items-center justify-center py-4">
        <Typography.Text type="tertiary" size="small">
          {env.copyright}
        </Typography.Text>
      </Layout.Footer>
    </Layout>
  );
}
