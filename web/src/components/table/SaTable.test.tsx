import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MemoryRouter } from 'react-router';

import { setToken } from '@/lib/authToken';
import type { TableQueryState } from '@/hooks/useTable';
import { SaTable, type TableColumn } from './SaTable';

interface Row {
  id: string;
  name: string;
}

const BASE_QUERY: TableQueryState = { page: 1, pageSize: 20, sorter: null, filters: {} };

const COLUMNS: TableColumn<Row>[] = [
  { title: '名称', dataIndex: 'name', sorter: true },
  { title: '密级', dataIndex: 'secret', permission: 'system:secret:read' },
];

function renderTable(props: Partial<Parameters<typeof SaTable<Row>>[0]>, token = 'mock-admin-token') {
  setToken(token);
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const wrap = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  );
  return render(
    <SaTable<Row>
      columns={COLUMNS}
      rows={[{ id: '1', name: '第一行' }]}
      query={BASE_QUERY}
      total={30}
      {...props}
    />,
    { wrapper: wrap },
  );
}

describe('SaTable', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('renders rows and hides columns without permission', async () => {
    renderTable({});
    expect(await screen.findByText('第一行')).toBeInTheDocument();
    // 无 system:secret:read 这条权限码概念 —— admin 是 '*' 通配所以能看见。
    expect(await screen.findByText('密级')).toBeInTheDocument();
  });

  it('hides permission-gated column for a user without the code', async () => {
    renderTable({}, 'mock-viewer-token');
    expect(await screen.findByText('第一行')).toBeInTheDocument();
    expect(screen.queryByText('密级')).not.toBeInTheDocument();
  });

  it('emits a new query when pagination changes', async () => {
    const onQueryChange = vi.fn();
    renderTable({ onQueryChange });
    expect(await screen.findByText('第一行')).toBeInTheDocument();
    await userEvent.click(screen.getByText('2'));
    expect(onQueryChange).toHaveBeenCalledWith({ ...BASE_QUERY, page: 2, pageSize: 20 });
  });

  it('shows the error state instead of the table', async () => {
    renderTable({ rows: [], error: new Error('boom'), onRetry: () => {} });
    expect(await screen.findByText('出了点问题')).toBeInTheDocument();
    expect(screen.getByText('重试')).toBeInTheDocument();
  });

  it('renders the sort control for sortable columns', async () => {
    renderTable({
      query: { ...BASE_QUERY, sorter: { field: 'name', order: 'ascend' } },
    });
    await screen.findByText('第一行');
    expect(document.querySelector('.semi-table-column-sorter')).not.toBeNull();
  });
});
