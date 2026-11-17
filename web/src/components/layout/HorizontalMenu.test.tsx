import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { HorizontalMenu } from './HorizontalMenu';

function renderMenu(entry = '/') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <HorizontalMenu />,
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

describe('HorizontalMenu', () => {
  afterEach(() => {
    clearToken();
  });

  it('以横向模式渲染一级菜单', async () => {
    setToken('mock-admin-token');
    const { container } = renderMenu('/');

    const nav = await waitFor(() => {
      const el = container.querySelector('.semi-navigation-horizontal');
      expect(el).toBeTruthy();
      return el as HTMLElement;
    });
    expect(nav).toBeInTheDocument();
    expect(screen.getByText('工作台')).toBeInTheDocument();
    expect(screen.getByText('系统设置')).toBeInTheDocument();
  });

  it('点击一级页面项跳路由', async () => {
    setToken('mock-admin-token');
    const { router } = renderMenu('/preferences');

    await screen.findByText('工作台');
    await userEvent.click(screen.getByText('工作台'));

    await waitFor(() => expect(router.state.location.pathname).toBe('/'));
  });
});
