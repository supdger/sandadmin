import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { SearchBar } from './SearchBar';
import type { FormFieldConfig } from './FieldItem';

const MANY_FIELDS: FormFieldConfig[] = [
  { type: 'input', field: 'keyword', label: '关键词', placeholder: '搜名称' },
  { type: 'input', field: 'owner', label: '负责人' },
  { type: 'input', field: 'city', label: '城市' },
  { type: 'input', field: 'remark', label: '备注' },
  { type: 'input', field: 'phone', label: '手机号' },
];

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('SearchBar', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('collapses fields beyond the third and expands on demand', async () => {
    render(<SearchBar fields={MANY_FIELDS} onSearch={() => {}} />, { wrapper: wrap });
    expect(await screen.findByText('关键词')).toBeInTheDocument();
    expect(screen.queryByText('手机号')).not.toBeInTheDocument();

    await userEvent.click(screen.getByText('展开'));
    expect(await screen.findByText('手机号')).toBeInTheDocument();
    expect(screen.getByText('收起')).toBeInTheDocument();
  });

  it('emits compacted values on search and clears them on reset', async () => {
    const onSearch = vi.fn();
    const onReset = vi.fn();
    render(<SearchBar fields={MANY_FIELDS} onSearch={onSearch} onReset={onReset} />, {
      wrapper: wrap,
    });
    await userEvent.type(await screen.findByPlaceholderText('搜名称'), '合同');
    await userEvent.click(screen.getByText('查询'));
    await waitFor(() => expect(onSearch).toHaveBeenLastCalledWith({ keyword: '合同' }));

    await userEvent.click(screen.getByText('重置'));
    await waitFor(() => expect(onSearch).toHaveBeenLastCalledWith({}));
    expect(onReset).toHaveBeenCalledOnce();
  });
});
