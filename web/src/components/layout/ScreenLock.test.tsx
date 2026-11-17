import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken } from '@/lib/authToken';
import { getGlobalUi, lockScreen, resetGlobalUi } from './globalUi';
import { ScreenLock } from './ScreenLock';

function renderLock(entry = '/system/user') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/login', element: <div>登录页</div> },
      {
        element: <ScreenLock />,
        children: [
          { path: '/', element: <div /> },
          { path: '/system/user', element: <div /> },
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

describe('ScreenLock', () => {
  afterEach(() => {
    clearToken();
    resetGlobalUi();
  });

  it('解锁先清本地锁再跳登录页带回来地址', async () => {
    lockScreen();
    const router = renderLock('/system/user');

    await userEvent.click(await screen.findByRole('button', { name: '解锁' }));

    expect(getGlobalUi().screenLocked).toBe(false);
    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
      expect(router.state.location.search).toContain('redirect=');
      expect(decodeURIComponent(router.state.location.search)).toContain(
        '/system/user',
      );
    });
  });
});
