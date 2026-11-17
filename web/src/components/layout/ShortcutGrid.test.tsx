import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
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
});
