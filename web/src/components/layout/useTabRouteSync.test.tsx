import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, waitFor } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { getTabsState, resetTabs } from '@/stores/tabs';
import { useTabRouteSync } from './useTabRouteSync';

function Probe({ enabled }: { enabled: boolean }) {
  useTabRouteSync(enabled);
  return null;
}

function renderAt(entry: string, enabled = true) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <Probe enabled={enabled} />,
        children: [
          { path: '/', element: <div /> },
          { path: '/preferences', element: <div /> },
          { path: '/secret', element: <div />, handle: { title: 'workbench.title' } },
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

describe('useTabRouteSync', () => {
  afterEach(() => {
    clearToken();
    resetTabs();
  });

  it('路由命中菜单节点时用菜单标题登记标签', async () => {
    setToken('mock-admin-token');
    renderAt('/preferences');

    await waitFor(() => {
      const tab = getTabsState().tabs.find((item) => item.key === '/preferences');
      expect(tab?.title).toBe('偏好设置');
    });
  });

  it('路由不在菜单树时用 handle.title 兜底', async () => {
    setToken('mock-admin-token');
    renderAt('/secret');

    await waitFor(() => {
      const tab = getTabsState().tabs.find((item) => item.key === '/secret');
      expect(tab?.title).toBe('工作台');
    });
  });

  it('enabled=false 时不登记', async () => {
    setToken('mock-admin-token');
    renderAt('/preferences', false);
    await new Promise((resolve) => setTimeout(resolve, 50));
    expect(getTabsState().tabs).toHaveLength(0);
  });
});
