import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from 'msw';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it } from 'vitest';

import { clearToken, getToken } from '@/lib/authToken';
import { fail } from '@/mocks/handlers/_shared';
import { __authMock } from '@/mocks/handlers/auth';
import { server } from '@/mocks/server';
import LoginPage from './LoginPage';

function renderLogin(entry = '/login') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  const router = createMemoryRouter(
    [
      { path: '/login', element: <LoginPage /> },
      { path: '/', element: <div>首页占位</div> },
      { path: '/force-change-password', element: <div>强制改密页</div> },
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

async function submitLogin(username: string, password: string) {
  const user = userEvent.setup();
  await user.clear(await screen.findByPlaceholderText('请输入用户名'));
  await user.type(screen.getByPlaceholderText('请输入用户名'), username);
  await user.clear(screen.getByPlaceholderText('请输入密码'));
  await user.type(screen.getByPlaceholderText('请输入密码'), password);
  await user.click(screen.getByRole('button', { name: '登录' }));
}

describe('LoginPage', () => {
  beforeEach(() => {
    clearToken();
    __authMock.reset();
    // The mock defaults dev login to available; the plain-mode cases below
    // exercise the production experience, so switch it off here.
    __authMock.setDevLoginAvailable(false);
  });

  it('shows validation messages when submitting an empty form', async () => {
    renderLogin();
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: '登录' }));

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

describe('LoginPage dev entry', () => {
  beforeEach(() => {
    clearToken();
    // reset() restores the mock default: dev login available.
    __authMock.reset();
  });

  it('shows the dev banner, prefills the form and focuses the entry button', async () => {
    renderLogin();

    expect(
      await screen.findByText('开发模式：仅本机可用，正式环境会自动关闭'),
    ).toBeInTheDocument();
    expect(await screen.findByPlaceholderText('请输入用户名')).toHaveValue('admin');
    expect(screen.getByPlaceholderText('请输入密码')).toHaveValue('••••••••');

    const entry = screen.getByRole('button', { name: '一键进入' });
    await waitFor(() => {
      expect(entry).toHaveFocus();
    });
    expect(screen.queryByRole('button', { name: '登录' })).not.toBeInTheDocument();
  });

  it('enters the workbench when Enter is pressed on the focused entry button', async () => {
    const router = renderLogin();
    await screen.findByRole('button', { name: '一键进入' });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: '一键进入' })).toHaveFocus();
    });

    const user = userEvent.setup();
    await user.keyboard('{Enter}');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/');
    });
    expect(getToken()).toBe('mock-admin-token');
  });

  it('enters the workbench by clicking the entry button', async () => {
    const router = renderLogin();
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: '一键进入' }));

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/');
    });
    expect(getToken()).toBe('mock-admin-token');
  });

  it('auto-enters once when the URL carries ?dev=1', async () => {
    const router = renderLogin('/login?dev=1');

    await waitFor(() => {
      expect(router.state.location.pathname).toBe('/');
    });
    expect(getToken()).toBe('mock-admin-token');
  });

  it('keeps the entry button usable and shows the error when ?dev=1 auto entry fails', async () => {
    server.use(http.post('/api/auth/dev-login', () => fail(4002, '开发账号不可用')));
    renderLogin('/login?dev=1');

    expect(await screen.findByText('开发账号不可用')).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: '一键进入' })).toBeInTheDocument();
    expect(getToken()).toBeNull();
  });

  it('falls back to the production form when the dev login endpoint is gone', async () => {
    server.use(
      http.post('/api/auth/dev-login', () => new Response(null, { status: 404 })),
    );
    renderLogin('/login?dev=1');

    expect(await screen.findByRole('button', { name: '登录' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '一键进入' })).not.toBeInTheDocument();
    expect(
      screen.queryByText('开发模式：仅本机可用，正式环境会自动关闭'),
    ).not.toBeInTheDocument();
  });

  it('clears the prefill and switches to the plain form via the other-account link', async () => {
    renderLogin();
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: '用其他账号登录' }));

    expect(await screen.findByRole('button', { name: '登录' })).toBeInTheDocument();
    expect(screen.getByPlaceholderText('请输入用户名')).toHaveValue('');
    expect(screen.getByPlaceholderText('请输入密码')).toHaveValue('');
    expect(screen.queryByRole('button', { name: '一键进入' })).not.toBeInTheDocument();
    expect(screen.queryByTestId('captcha-image')).not.toBeInTheDocument();
    // The environment is still dev mode — the warning banner stays.
    expect(
      screen.getByText('开发模式：仅本机可用，正式环境会自动关闭'),
    ).toBeInTheDocument();
  });

  it('never shows a captcha in dev mode however many attempts fail', async () => {
    renderLogin();
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: '用其他账号登录' }));

    for (let i = 0; i < 5; i += 1) {
      await submitLogin('admin', `bad-${i}`);
      await screen.findByText(/用户名或密码不正确|请先完成下方验证/);
    }

    expect(screen.queryByTestId('captcha-image')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '一键进入' })).not.toBeInTheDocument();
  });
});
