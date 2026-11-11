import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { DeptsPage } from './DeptsPage';

function renderPage(initialEntry = '/system/dept') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <DeptsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('DeptsPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('renders the dept tree with leaders and user counts', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('沙盒网络')).toBeInTheDocument();
    expect(screen.getByText('技术部')).toBeInTheDocument();
    // 默认展开前两层：后端组/前端组（第三层）也在树里。
    expect(screen.getByText('后端组')).toBeInTheDocument();
    expect(screen.getByText('运营部')).toBeInTheDocument();
    // 无负责人显示「未设置」。
    expect(screen.getAllByText('未设置').length).toBeGreaterThan(0);
  });

  it('opens the create drawer from the header button', async () => {
    setToken('mock-admin-token');
    renderPage();

    await screen.findByText('沙盒网络');
    await userEvent.click(screen.getByRole('button', { name: /添加部门/ }));

    // 抽屉表单字段（「部门名称」同时是表格列名，用只在表单出现的标签断言）。
    expect(await screen.findByText('上级部门')).toBeInTheDocument();
    expect(screen.getByText('联系电话')).toBeInTheDocument();
  });

  it('hides write controls for view-only accounts', async () => {
    setToken('mock-viewer-token');
    renderPage();

    await screen.findByText('沙盒网络');
    expect(screen.queryByRole('button', { name: /添加部门/ })).not.toBeInTheDocument();
    expect(screen.queryByText('⋮⋮')).not.toBeInTheDocument();
  });
});
