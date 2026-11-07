import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it } from 'vitest';

import { clearToken, getToken } from '@/lib/authToken';
import { __authMock } from '@/mocks/handlers/auth';
import LoginPage from './LoginPage';

function renderLogin() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/login', element: <LoginPage /> },
      { path: '/', element: <div>首页占位</div> },
      { path: '/force-change-password', element: <div>强制改密页</div> },
    ],
    { initialEntries: ['/login'] },
  );
  render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  );
  return router;
}

async function submitLogin(username: string, password: string) {
  const user = userEvent.setup();
  await user.clear(screen.getByPlaceholderText('请输入用户名'));
  await user.type(screen.getByPlaceholderText('请输入用户名'), username);
  await user.clear(screen.getByPlaceholderText('请输入密码'));
  await user.type(screen.getByPlaceholderText('请输入密码'), password);
  await user.click(screen.getByRole('button', { name: '登录' }));
}

describe('LoginPage', () => {
  beforeEach(() => {
    clearToken();
    __authMock.reset();
  });

  it('shows validation messages when submitting an empty form', async () => {
    renderLogin();
    const user = userEvent.setup();
    await user.click(screen.getByRole('button', { name: '登录' }));

    expect(await screen.findAllByText('请输入用户名')).not.toHaveLength(0);
    expect(await screen.findAllByText('请输入密码')).not.toHaveLength(0);
  });

  it('stores the token and navigates home on a successful login', async () => {
    const router = renderLogin();
    await submitLogin('admin', 'Admin@123456');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/');
    });
    expect(getToken()).toBe('mock-admin-token');
  });

  it('shows the backend message on a wrong password', async () => {
    renderLogin();
    await submitLogin('admin', 'wrong-pass');

    expect(await screen.findByText('用户名或密码不正确')).toBeInTheDocument();
    expect(getToken()).toBeNull();
  });

  it('shows the captcha after three failed attempts', async () => {
    renderLogin();
    await submitLogin('admin', 'bad-1');
    await screen.findByText('用户名或密码不正确');
    await submitLogin('admin', 'bad-2');
    await submitLogin('admin', 'bad-3');

    expect(await screen.findByTestId('captcha-image')).toBeInTheDocument();
  });

  it('logs in after solving the captcha', async () => {
    const router = renderLogin();
    await submitLogin('admin', 'bad-1');
    await submitLogin('admin', 'bad-2');
    await submitLogin('admin', 'bad-3');

    const image = await screen.findByTestId('captcha-image');
    const captchaId = image.getAttribute('data-captcha-id') as string;
    const centers = __authMock.captchaCenters(captchaId);
    expect(centers).not.toBeNull();

    const box = screen.getByTestId('captcha-box');
    for (let i = 0; i < 3; i += 1) {
      fireEvent.click(box, {
        clientX: (centers as number[])[i * 2],
        clientY: (centers as number[])[i * 2 + 1],
      });
    }
    await screen.findByTestId('captcha-ok');

    const user = userEvent.setup();
    await user.clear(screen.getByPlaceholderText('请输入密码'));
    await user.type(screen.getByPlaceholderText('请输入密码'), 'Admin@123456');
    await user.click(screen.getByRole('button', { name: '登录' }));

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/');
    });
    expect(getToken()).toBe('mock-admin-token');
  });

  it('redirects to force-change-password for a user who must change password', async () => {
    const router = renderLogin();
    await submitLogin('newbie', 'Admin@123456');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/force-change-password');
    });
    expect(getToken()).toBe('mock-newbie-token');
  });

  it('locks the account after ten failures and shows the wait hint', async () => {
    renderLogin();
    for (let i = 0; i < 10; i += 1) {
      // sequential on purpose — the mock counts attempts in order
      await submitLogin('locked-user', `bad-${i}`);
    }

    expect(await screen.findByText(/账号已锁定/)).toBeInTheDocument();
  }, 20000);
});
