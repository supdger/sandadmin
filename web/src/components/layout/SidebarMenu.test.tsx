import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { resetLayoutSettings } from '@/stores/layout';
import { SidebarMenu } from './SidebarMenu';

function renderMenu(entry = '/', collapsed?: boolean) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <SidebarMenu collapsed={collapsed} />,
        children: [
          { path: '/', element: <div /> },
          { path: '/preferences', element: <div /> },
          { path: '*', element: <div /> },
        ],
      },
    ],
    { initialEntries: [entry] },
  );
  const { container } = render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return { router, container };
}

describe('SidebarMenu', () => {
  afterEach(() => {
    clearToken();
    resetLayoutSettings();
  });

  it('渲染 /me/menus 菜单树并随路由选中', async () => {
    setToken('mock-admin-token');
    const { container } = renderMenu('/');

    const nav = await waitFor(() => {
      const el = container.querySelector('.semi-navigation');
      expect(el).toBeTruthy();
      return el as HTMLElement;
    });
    expect(screen.getByText('工作台')).toBeInTheDocument();
    expect(screen.getByText('系统设置')).toBeInTheDocument();
    expect(nav).toBeInTheDocument();
  });

  it('点击菜单项跳对应路由', async () => {
    setToken('mock-admin-token');
    const { router } = renderMenu('/');

    await screen.findByText('系统设置');
    await userEvent.click(screen.getByText('系统设置'));
    await userEvent.click(await screen.findByText('偏好设置'));

    await waitFor(() => expect(router.state.location.pathname).toBe('/preferences'));
  });

  it('菜单请求失败时给重试入口', async () => {
    setToken('mock-guest-token');
    // guest 无权限但接口仍返回空树；这里用直接覆盖制造失败。
    const { server } = await import('@/mocks/server');
    const { http, HttpResponse } = await import('msw');
    const { env } = await import('@/config/env');
    server.use(
      http.get(`${env.apiBaseUrl}/admin/v1/me/menus`, () =>
        HttpResponse.json(
          { code: 'MENU_UNAVAILABLE', message: '菜单暂不可用', request_id: 't' },
          { status: 422 },
        ),
      ),
    );

    renderMenu('/');
    expect(await screen.findByText('菜单加载失败了')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '重试' })).toBeInTheDocument();
  });
});
