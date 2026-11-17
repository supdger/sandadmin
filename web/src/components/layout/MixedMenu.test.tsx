import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { getGlobalUi, resetGlobalUi } from './globalUi';
import { MixedMenu } from './MixedMenu';

function renderMenu(entry = '/') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      {
        element: <MixedMenu />,
        children: [
          { path: '/', element: <div /> },
          { path: '/data/dict', element: <div /> },
          { path: '*', element: <div /> },
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

describe('MixedMenu', () => {
  afterEach(() => {
    clearToken();
    resetGlobalUi();
  });

  it('只渲染顶层节点', async () => {
    setToken('mock-admin-token');
    renderMenu('/');

    expect(await screen.findByText('工作台')).toBeInTheDocument();
    expect(screen.getByText('数据管理')).toBeInTheDocument();
    // 二级节点不出现在横排菜单里。
    expect(screen.queryByText('选项管理')).not.toBeInTheDocument();
  });

  it('点容器节点激活该栏目并跳到第一个叶子页', async () => {
    setToken('mock-admin-token');
    const router = renderMenu('/');

    await screen.findByText('数据管理');
    await userEvent.click(screen.getByText('数据管理'));

    await waitFor(() => expect(router.state.location.pathname).toBe('/data/dict'));
    await waitFor(() => expect(getGlobalUi().activeTopKey).toBe('data'));
  });
});
