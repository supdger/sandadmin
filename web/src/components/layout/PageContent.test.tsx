import { useState } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { resetTabs } from '@/stores/tabs';
import { resetGlobalUi, setTabsEnabled } from './globalUi';
import { PageContent } from './PageContent';

function Counter() {
  const [count, setCount] = useState(0);
  return (
    <button type="button" onClick={() => setCount((n) => n + 1)}>
      计数:{count}
    </button>
  );
}

function renderContent(entry = '/') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <PageContent />,
        children: [
          { path: '/', element: <div>首页体</div> },
          { path: '/counter', element: <Counter /> },
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

describe('PageContent', () => {
  afterEach(() => {
    clearToken();
    resetTabs();
    resetGlobalUi();
  });

  it('多标签关闭时只渲染当前页', () => {
    setTabsEnabled(false);
    renderContent('/');
    expect(screen.getByText('首页体')).toBeInTheDocument();
  });

  it('多标签开启时路由变化自动登记标签', async () => {
    setToken('mock-admin-token');
    setTabsEnabled(true);
    const router = renderContent('/');
    await waitFor(() => expect(screen.getByText('首页体')).toBeInTheDocument());

    await router.navigate('/counter');
    await waitFor(() => expect(screen.getByRole('button', { name: '计数:0' })).toBeInTheDocument());
  });

  it('标签间切换保留页面本地状态', async () => {
    setToken('mock-admin-token');
    setTabsEnabled(true);
    const router = renderContent('/counter');

    await userEvent.click(await screen.findByRole('button', { name: '计数:0' }));
    expect(screen.getByRole('button', { name: '计数:1' })).toBeInTheDocument();

    await router.navigate('/');
    await screen.findByText('首页体');
    await router.navigate('/counter');

    await waitFor(() =>
      expect(screen.getByRole('button', { name: '计数:1' })).toBeInTheDocument(),
    );
  });
});
