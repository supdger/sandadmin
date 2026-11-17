import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { adminGet } from '@/lib/adminClient';
import { setToken } from '@/lib/authToken';
import type { Page } from '@/api/v1/types';
import { useTable, type TableQueryState } from './useTable';

interface Row {
  id: string;
  name: string;
  status: string;
  create_time: string;
}

function fetchRows(query: TableQueryState): Promise<Page<Row>> {
  return adminGet<Page<Row>>('/test-rows', {
    query: {
      page: query.page,
      page_size: query.pageSize,
      keyword: query.filters.keyword,
      status: query.filters.status,
      sort_field: query.sorter?.field,
      sort_order: query.sorter?.order === 'descend' ? 'desc' : 'asc',
    },
  });
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('useTable', () => {
  beforeEach(() => {
    setToken('mock-admin-token');
  });

  it('fetches the first page with rows and total', async () => {
    const { result } = renderHook(() => useTable<Row>({ queryKey: ['test-rows'], fetcher: fetchRows }), { wrapper });
    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(result.current.rows).toHaveLength(20);
    expect(result.current.total).toBe(57);
    expect(result.current.query.page).toBe(1);
  });

  it('turns filters into query params and resets page to 1', async () => {
    const { result } = renderHook(
      () => useTable<Row>({ queryKey: ['test-rows'], fetcher: fetchRows, pageSize: 10 }),
      { wrapper },
    );
    await waitFor(() => expect(result.current.loading).toBe(false));

    act(() => result.current.setQuery({ page: 3 }));
    await waitFor(() => expect(result.current.query.page).toBe(3));

    act(() => result.current.setQuery({ filters: { status: 'disabled' } }));
    await waitFor(() => expect(result.current.query.page).toBe(1));
    await waitFor(() => expect(result.current.rows.length).toBeGreaterThan(0));
    expect(result.current.rows.every((row) => row.status === 'disabled')).toBe(true);
    expect(result.current.total).toBe(19);
  });

  it('maps sorter into the request and clears selection on query change', async () => {
    const { result } = renderHook(
      () => useTable<Row>({ queryKey: ['test-rows'], fetcher: fetchRows, pageSize: 5 }),
      { wrapper },
    );
    await waitFor(() => expect(result.current.loading).toBe(false));

    act(() => result.current.setSelectedKeys(['row-001', 'row-002']));
    expect(result.current.selectedKeys).toHaveLength(2);

    act(() =>
      result.current.setQuery({ sorter: { field: 'create_time', order: 'descend' } }),
    );
    expect(result.current.selectedKeys).toEqual([]);
    await waitFor(() =>
      expect(result.current.rows[0]?.create_time).toBe('2026-01-28T08:00:00+08:00'),
    );
  });

  it('refresh refetches and clears selection', async () => {
    const { result } = renderHook(() => useTable<Row>({ queryKey: ['test-rows'], fetcher: fetchRows }), { wrapper });
    await waitFor(() => expect(result.current.loading).toBe(false));

    act(() => result.current.setSelectedKeys(['row-001']));
    act(() => result.current.refresh());
    expect(result.current.selectedKeys).toEqual([]);
    await waitFor(() => expect(result.current.fetching).toBe(false));
    expect(result.current.rows).toHaveLength(20);
  });
});
