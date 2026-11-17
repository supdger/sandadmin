import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { PluginHostPage } from './PluginHostPage';

const v1 = `${env.apiBaseUrl}/admin/v1`;

function renderHost(entry: string) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/plugin/:pluginCode/*', element: <PluginHostPage /> },
      { path: '/403', element: <div>无权限页</div> },
    ],
    { initialEntries: [entry] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return router;
}

function mockMenusWithoutPlugin() {
  server.use(
    http.get(`${v1}/me/menus`, () =>
      HttpResponse.json({
        data: {
          menu_version: 1,
          items: [
            {
              key: 'home',
              type: 'page',
              title: '工作台',
              icon: 'home',
              path: '/',
              url: null,
            },
          ],
        },
        request_id: 't',
      }),
    ),
  );
}

describe('PluginHostPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('renders the plugin index page when installed and permitted', async () => {
    setToken('mock-admin-token');
    renderHost('/plugin/sand-hello');

    expect(await screen.findByText('你好，插件')).toBeInTheDocument();
  });

  it('renders plugin-declared sub routes', async () => {
    setToken('mock-admin-token');
    renderHost('/plugin/sand-hello/about');

    expect(await screen.findByText('关于你好插件')).toBeInTheDocument();
  });

  it('shows the not-installed 404 when the menu tree lacks the plugin node', async () => {
    setToken('mock-admin-token');
    mockMenusWithoutPlugin();
    renderHost('/plugin/sand-hello');

    expect(await screen.findByText('插件未安装')).toBeInTheDocument();
  });

  it('redirects to /403 when the user lacks the plugin permission', async () => {
    setToken('mock-viewer-token');
    const router = renderHost('/plugin/sand-hello');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/403');
    });
  });

  it('shows the not-found 404 for an unregistered plugin code', async () => {
    setToken('mock-admin-token');
    renderHost('/plugin/ghost-plugin');

    expect(await screen.findByText('插件页面不存在')).toBeInTheDocument();
  });
});
