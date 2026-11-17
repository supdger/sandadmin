import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { ShortcutGrid } from './ShortcutGrid';

function renderGrid(entry = '/') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <ShortcutGrid />,
        children: [
          { path: '/', element: <div /> },
          { path: '/preferences', element: <div /> },
        ],
      },
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

describe('ShortcutGrid', () => {
  afterEach(() => {
    clearToken();
  });

  it('渲染服务端下发的快捷入口并点击跳路由', async () => {
    setToken('mock-admin-token');
    const router = renderGrid('/');

    expect(await screen.findByText('工作台')).toBeInTheDocument();
    expect(screen.getByText('偏好设置')).toBeInTheDocument();

    await userEvent.click(screen.getByText('偏好设置'));
    await waitFor(() => expect(router.state.location.pathname).toBe('/preferences'));
  });

  it('超管展示菜单树允许的全部入口', async () => {
    server.use(
      http.get('/api/admin/v1/dashboard/shortcuts', () =>
        HttpResponse.json({
          data: {
            mode: 'auto',
            items: [
              { menu_key: 'system:user', title: '用户管理', icon: null, link: '/system/user' },
              { menu_key: 'system:plugin', title: '插件管理', icon: null, link: '/system/plugins' },
            ],
          },
          request_id: 'test',
        }),
      ),
    );
    setToken('mock-admin-token');
    renderGrid('/');

    expect(await screen.findByText('用户管理')).toBeInTheDocument();
    expect(screen.getByText('插件管理')).toBeInTheDocument();
  });

  it('隐藏菜单树里不存在的入口（无权限不显示）', async () => {
    server.use(
      http.get('/api/admin/v1/dashboard/shortcuts', () =>
        HttpResponse.json({
          data: {
            mode: 'auto',
            items: [
              { menu_key: 'preferences', title: '偏好设置', icon: null, link: '/preferences' },
              { menu_key: 'system:plugin', title: '插件管理', icon: null, link: '/system/plugins' },
            ],
          },
          request_id: 'test',
        }),
      ),
    );
    // viewer 有 workbench/preferences 与若干 index 权限，但无 system:plugin:index。
    setToken('mock-viewer-token');
    renderGrid('/');

    expect(await screen.findByText('偏好设置')).toBeInTheDocument();
    expect(screen.queryByText('插件管理')).not.toBeInTheDocument();
  });

  it('空权限集不显示任何入口', async () => {
    setToken('mock-guest-token');
    renderGrid('/');

    expect(await screen.findByText('暂无快捷入口')).toBeInTheDocument();
    expect(screen.queryByText('偏好设置')).not.toBeInTheDocument();
  });
});
