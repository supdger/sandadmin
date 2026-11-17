import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { RouteBreadcrumb } from './RouteBreadcrumb';

function renderAt(entry: string, extra?: RouteObject[]) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <RouteBreadcrumb />,
        children: [
          { path: '/', element: <div /> },
          { path: '/system/user', element: <div /> },
          { path: '/preferences', element: <div /> },
          { path: '/secret', element: <div />, handle: { title: 'workbench.title' } },
          ...(extra ?? []),
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

describe('RouteBreadcrumb', () => {
  afterEach(() => {
    clearToken();
  });

  it('按菜单链渲染且末级不可点击', async () => {
    setToken('mock-admin-token');
    renderAt('/system/user');

    expect(await screen.findByText('首页')).toBeInTheDocument();
    const last = await screen.findByText('用户管理');
    // 末级没有链接节点，只是定位标识。
    expect(last.closest('a')).toBeNull();
  });

  it('路由不在菜单树时用 handle.title 兜底', async () => {
    setToken('mock-admin-token');
    renderAt('/secret');

    expect(await screen.findByText('首页')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText('工作台')).toBeInTheDocument());
  });

  it('首页自身只显示一级', async () => {
    setToken('mock-admin-token');
    renderAt('/');
    await screen.findByText('首页');
    expect(screen.queryByText('工作台')).not.toBeInTheDocument();
  });
});
