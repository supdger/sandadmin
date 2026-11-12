import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { AuditLogPage } from './AuditLogPage';

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <AuditLogPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('AuditLogPage', () => {
  beforeEach(() => {
    resetV1Mock();
    setToken('mock-admin-token');
  });
  afterEach(() => clearToken());

  it('操作列展示人话，不出现 URL/方法/参数', async () => {
    renderPage();
    expect(await screen.findByText('停用了用户张三')).toBeInTheDocument();
    expect(screen.getByText('新建了角色「运营专员」')).toBeInTheDocument();
    expect(screen.queryByText(/PUT|GET|POST|DELETE|http/i)).not.toBeInTheDocument();
  });

  it('详情抽屉展示改前改后，密码只显示已修改', async () => {
    renderPage();
    await userEvent.click(await screen.findByText('重置了用户李四的密码'));
    expect(await screen.findByText('改前')).toBeInTheDocument();
    expect(screen.getByText('改后')).toBeInTheDocument();
    expect(screen.getAllByText('已修改').length).toBeGreaterThan(0);
    expect(screen.queryByText(/\*\*\*/)).not.toBeInTheDocument();
  });

  it('更多条件展开功能与结果筛选', async () => {
    renderPage();
    await userEvent.click(await screen.findByRole('button', { name: '更多条件' }));
    expect(await screen.findByRole('button', { name: '收起条件' })).toBeInTheDocument();
  });
});
