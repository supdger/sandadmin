import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { LoginLogPage } from './LoginLogPage';

function renderPage(initial = '/system/login-log') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initial]}>
        <LoginLogPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('LoginLogPage', () => {
  beforeEach(() => {
    resetV1Mock();
    setToken('mock-admin-token');
  });
  afterEach(() => clearToken());

  it('列表不显示地址列，失败原因按后端人话展示', async () => {
    renderPage('/system/login-log?login_time_from=2026-01-01&login_time_to=2026-01-11');
    expect(await screen.findByText('密码不对')).toBeInTheDocument();
    expect(screen.getByText('没有这个账号')).toBeInTheDocument();
    // 表头不出现 IP/地址；列表不含地址数字。
    expect(screen.queryByText('登录设备地址')).not.toBeInTheDocument();
    expect(screen.queryByText(/10\.0\.0\.|203\.0\.113\./)).not.toBeInTheDocument();
    // reason 为 null 的失败行兜底「没有成功」。
    expect(screen.getAllByText('没有成功').length).toBeGreaterThan(0);
  });

  it('点开详情抽屉可见登录设备地址', async () => {
    renderPage('/system/login-log?login_time_from=2026-01-01&login_time_to=2026-01-11');
    const cell = await screen.findByText('密码不对');
    await userEvent.click(cell);
    expect(await screen.findByText('登录设备地址')).toBeInTheDocument();
    // 「登录设备地址」渲染 ip（ll-3 = 10.0.0.32）；user_agent 原文不在页面出现。
    expect(screen.getByText('10.0.0.32')).toBeInTheDocument();
    expect(screen.queryByText(/Mozilla\//)).not.toBeInTheDocument();
    const closeButton = screen.getByRole('button', { name: '关闭' });
    const viewButton = screen.getByRole('button', { name: '查看用户' });
    // 底部按钮固定右下：「关闭」在前，主按钮「查看用户」最右。
    expect(closeButton.compareDocumentPosition(viewButton) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
  });
});
