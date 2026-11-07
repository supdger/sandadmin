import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it } from 'vitest';

import { clearToken, getToken, setToken } from '@/lib/authToken';
import { __authMock } from '@/mocks/handlers/auth';
import ForceChangePasswordPage from './ForceChangePasswordPage';

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/force-change-password', element: <ForceChangePasswordPage /> },
      { path: '/login', element: <div>登录页占位</div> },
    ],
    { initialEntries: ['/force-change-password'] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return router;
}

describe('ForceChangePasswordPage', () => {
  beforeEach(() => {
    clearToken();
    __authMock.reset();
  });

  it('redirects to /login when there is no token', async () => {
    const router = renderPage();
    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
  });

  it('lists the password policy in plain language', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('至少 8 个字符')).toBeInTheDocument();
    expect(screen.getByText('同时包含大写和小写字母')).toBeInTheDocument();
    expect(screen.getByText('至少包含一个数字')).toBeInTheDocument();
  });

  it('rejects a weak new password before submitting', async () => {
    setToken('mock-admin-token');
    renderPage();
    await screen.findByText('新密码需要满足：');

    const user = userEvent.setup();
    await user.type(screen.getByPlaceholderText('请输入当前密码'), 'Admin@123456');
    await user.type(screen.getByPlaceholderText('请输入新密码'), 'short');
    await user.type(screen.getByPlaceholderText('请再输入一次新密码'), 'short');
    await user.click(screen.getByRole('button', { name: '确认修改' }));

    await waitFor(() => {
      expect(screen.getAllByText('至少 8 个字符').length).toBeGreaterThan(1);
    });
    expect(getToken()).toBe('mock-admin-token');
  });

  it('clears the token and returns to /login after a successful change', async () => {
    setToken('mock-admin-token');
    const router = renderPage();
    await screen.findByText('新密码需要满足：');

    const user = userEvent.setup();
    await user.type(screen.getByPlaceholderText('请输入当前密码'), 'Admin@123456');
    await user.type(screen.getByPlaceholderText('请输入新密码'), 'NewPass@2026');
    await user.type(screen.getByPlaceholderText('请再输入一次新密码'), 'NewPass@2026');
    await user.click(screen.getByRole('button', { name: '确认修改' }));

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/login');
    });
    expect(getToken()).toBeNull();
  });

  it('shows the backend message when the current password is wrong', async () => {
    setToken('mock-admin-token');
    renderPage();
    await screen.findByText('新密码需要满足：');

    const user = userEvent.setup();
    await user.type(screen.getByPlaceholderText('请输入当前密码'), 'wrong-old');
    await user.type(screen.getByPlaceholderText('请输入新密码'), 'NewPass@2026');
    await user.type(screen.getByPlaceholderText('请再输入一次新密码'), 'NewPass@2026');
    await user.click(screen.getByRole('button', { name: '确认修改' }));

    expect(await screen.findByText('当前密码不正确')).toBeInTheDocument();
    expect(getToken()).toBe('mock-admin-token');
  });
});
