import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { getLayoutSettings, resetLayoutSettings } from '@/stores/layout';
import { getGlobalUi, resetGlobalUi } from './globalUi';
import { HeaderBar } from './HeaderBar';

function renderBar(props?: Parameters<typeof HeaderBar>[0]) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <HeaderBar {...props} />,
        children: [
          { path: '/', element: <div /> },
          { path: '*', element: <div /> },
        ],
      },
    ],
    { initialEntries: ['/'] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return router;
}

describe('HeaderBar', () => {
  afterEach(() => {
    clearToken();
    resetLayoutSettings();
    resetGlobalUi();
    document.body.removeAttribute('theme-mode');
  });

  it('折叠触发器切换侧栏折叠态', async () => {
    setToken('mock-admin-token');
    renderBar();

    const trigger = await screen.findByRole('button', { name: '收起菜单' });
    await userEvent.click(trigger);
    expect(getLayoutSettings().sidebarCollapsed).toBe(true);
    expect(screen.getByRole('button', { name: '展开菜单' })).toBeInTheDocument();
  });

  it('设置齿轮打开设置面板', async () => {
    setToken('mock-admin-token');
    renderBar();

    await userEvent.click(await screen.findByRole('button', { name: '设置' }));
    expect(getGlobalUi().settingsOpen).toBe(true);
  });

  it('明暗切换只改本机偏好', async () => {
    setToken('mock-admin-token');
    renderBar();

    await userEvent.click(await screen.findByRole('button', { name: '切换到深色模式' }));
    expect(document.body.getAttribute('theme-mode')).toBe('dark');
    expect(localStorage.getItem('sandadmin.theme')).toBe('dark');
  });

  it('hideBreadcrumb 时不渲染面包屑', async () => {
    setToken('mock-admin-token');
    renderBar({ hideBreadcrumb: true, collapseTrigger: false });
    await waitFor(() => {
      expect(screen.queryByText('首页')).not.toBeInTheDocument();
    });
  });
});
