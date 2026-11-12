import '@douyinfe/semi-ui/react19-adapter';

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { ResultRoutePage } from './ResultRoutePage';

function renderAt(entry: RouteObject['path'] | { pathname: string; state?: unknown }) {
  const router = createMemoryRouter(
    [
      { path: '/result/success', element: <ResultRoutePage status="success" /> },
      { path: '/result/fail', element: <ResultRoutePage status="fail" /> },
      { path: '/login', element: <div>登录页</div> },
    ],
    { initialEntries: [entry as string] },
  );
  render(<RouterProvider router={router} />);
  return router;
}

describe('ResultRoutePage', () => {
  it('shows default copy for /result/success without router state', async () => {
    renderAt('/result/success');

    expect(await screen.findByText('操作成功')).toBeInTheDocument();
    expect(screen.getByText('一切顺利，可以继续下一步了')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '继续' })).toBeInTheDocument();
  });

  it('renders caller-provided title/description and navigates to next', async () => {
    const router = renderAt({
      pathname: '/result/success',
      state: { title: '安装完成', description: '现在可以登录了', next: '/login' },
    });

    expect(await screen.findByText('安装完成')).toBeInTheDocument();
    expect(screen.getByText('现在可以登录了')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: '继续' }));
    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
  });

  it('shows default fail copy for /result/fail', async () => {
    renderAt('/result/fail');

    expect(await screen.findByText('操作失败')).toBeInTheDocument();
  });
});
