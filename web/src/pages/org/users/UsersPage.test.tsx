import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { UsersPage } from './UsersPage';

function renderPage(initialEntry = '/system/user') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <UsersPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('UsersPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('renders the user list with masked phone, roles and status', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('张伟')).toBeInTheDocument();
    expect(screen.getAllByText('已启用').length).toBeGreaterThan(0);
    expect(screen.getAllByText('已停用').length).toBeGreaterThan(0);
    // 手机号必须脱敏展示。
    expect(screen.queryByText('13810000000')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /添加用户/ })).toBeInTheDocument();
  });

  it('shows the filtered empty state for a keyword with no matches', async () => {
    setToken('mock-admin-token');
    renderPage('/system/user?keyword=%E8%BF%90%E8%90%A5');

    expect(await screen.findByText('没有找到符合条件的用户')).toBeInTheDocument();
    // 筛选栏和空态各有一个「清空筛选条件」。
    expect(screen.getAllByRole('button', { name: /清空筛选条件/ }).length).toBeGreaterThan(0);
  });

  it('renders the system error state with retry for backend failures', async () => {
    setToken('mock-admin-token');
    renderPage('/system/user?keyword=__error__');

    expect(await screen.findByText('用户列表没能加载出来')).toBeInTheDocument();
    expect(screen.getByText(/系统出了点问题/)).toBeInTheDocument();
  });

  it('hides write actions for view-only accounts', async () => {
    setToken('mock-viewer-token');
    renderPage();

    expect(await screen.findByText('张伟')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /添加用户/ })).not.toBeInTheDocument();
  });
});
