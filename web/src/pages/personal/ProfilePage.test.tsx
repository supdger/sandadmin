import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { ProfilePage } from './ProfilePage';

if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}
if (typeof Range !== 'undefined' && !Range.prototype.getBoundingClientRect) {
  Range.prototype.getBoundingClientRect = () =>
    ({ width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0, x: 0, y: 0 }) as DOMRect;
}

const base = `${env.apiBaseUrl}/admin/v1`;

function renderPage(entry = '/profile') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[entry]}>
        <ProfilePage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('ProfilePage', () => {
  afterEach(() => {
    clearToken();
  });

  it('renders the basic tab with editable fields and read-only account info', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('个人设置')).toBeInTheDocument();
    expect(await screen.findByDisplayValue('管理员')).toBeInTheDocument();
    expect(screen.getByDisplayValue('13800000001')).toBeInTheDocument();
    // Read-only section explains admin-managed fields (all tabs stay mounted).
    expect(screen.getAllByText('以下信息由管理员维护').length).toBeGreaterThan(0);
    expect(screen.getAllByText('要改请联系管理员').length).toBeGreaterThan(0);
  });

  it('shows field-level backend errors verbatim on save', async () => {
    setToken('mock-admin-token');
    renderPage();
    await screen.findByDisplayValue('管理员');

    const phone = screen.getByDisplayValue('13800000001');
    await userEvent.clear(phone);
    await userEvent.type(phone, '13800000003'); // viewer's phone → duplicate
    await userEvent.click(screen.getByRole('button', { name: '保存' }));

    expect(
      await screen.findByText('这个手机号已经被别人用了'),
    ).toBeInTheDocument();
  });

  it('switches to the devices tab and lists sessions with the current marker', async () => {
    setToken('mock-admin-token');
    renderPage('/profile?tab=devices');

    expect(await screen.findByText('MacBook Pro · Chrome')).toBeInTheDocument();
    expect(screen.getByText('iPhone · Safari')).toBeInTheDocument();
    expect(screen.getAllByText('当前设备').length).toBe(1);
    // Non-current devices offer a revoke action; the current one does not.
    expect(screen.getAllByRole('button', { name: '退出这台设备' }).length).toBeGreaterThan(0);
    // 接口照收 ip/user_agent，但页面一律不显示（G5）。
    expect(screen.queryByText(/192\.0\.2\./)).toBeNull();
    expect(screen.queryByText(/Mozilla\//)).toBeNull();
    expect(document.body.innerHTML).not.toContain('192.0.2.21');
    expect(document.body.innerHTML).not.toContain('Mozilla/5.0');
  });

  it('security tab opens the change-password modal', async () => {
    setToken('mock-admin-token');
    renderPage('/profile?tab=security');

    expect(await screen.findByText('登录密码')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: '修改密码' }));
    expect(await screen.findByText('新密码需要满足：')).toBeInTheDocument();
  });

  it('shows the error state when the profile request fails', async () => {
    setToken('mock-admin-token');
    server.use(
      http.get(`${base}/me/profile`, () =>
        HttpResponse.json(
          { code: 'INTERNAL', message: 'x', request_id: 'r' },
          { status: 500 },
        ),
      ),
    );
    renderPage();

    // Every mounted tab hits the same failing query and shows its own error.
    expect(
      (await screen.findAllByText('系统出了点问题，请稍后再试')).length,
    ).toBeGreaterThan(0);
  });
});
