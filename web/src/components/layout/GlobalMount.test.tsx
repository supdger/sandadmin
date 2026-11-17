import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken } from '@/lib/authToken';
import { lockScreen, openSettings, resetGlobalUi } from './globalUi';
import { GlobalMount } from './GlobalMount';

function renderMount() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <GlobalMount />,
        children: [{ path: '/', element: <div /> }],
      },
    ],
    { initialEntries: ['/'] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
}

describe('GlobalMount', () => {
  afterEach(() => {
    clearToken();
    resetGlobalUi();
  });

  it('settingsOpen 时挂载设置面板', async () => {
    openSettings();
    renderMount();
    expect(await screen.findByText('界面设置')).toBeInTheDocument();
  });

  it('锁屏态挂载锁屏罩层', async () => {
    lockScreen();
    renderMount();
    expect(await screen.findByRole('dialog', { name: '屏幕已锁定' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '解锁' })).toBeInTheDocument();
  });
});
