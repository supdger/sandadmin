import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { UserSelect } from './UserSelect';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('UserSelect', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('loads user options and emits the picked id', async () => {
    const onChange = vi.fn();
    const { container } = render(<UserSelect onChange={onChange} />, { wrapper: wrap });
    // 同 DictSelect：等远程选项加载完再开下拉。
    await waitFor(() =>
      expect(container.querySelector('.semi-select .semi-spin')).toBeNull(),
    );
    await userEvent.click(container.querySelector('.semi-select') as HTMLElement);
    await userEvent.click(await screen.findByText('张三 · 后端组'));
    expect(onChange).toHaveBeenCalledWith('u-zhangsan');
  });

  it('keeps a selected value visible when it is not in the result list', async () => {
    render(
      <UserSelect value="u-old" currentLabels={{ 'u-old': '离职员工' }} />,
      { wrapper: wrap },
    );
    expect(await screen.findByText('离职员工')).toBeInTheDocument();
  });

  it('supports multiple selection', async () => {
    const onChange = vi.fn();
    const { container } = render(
      <UserSelect multiple value={[]} onChange={onChange} />,
      { wrapper: wrap },
    );
    await waitFor(() =>
      expect(container.querySelector('.semi-select .semi-spin')).toBeNull(),
    );
    await userEvent.click(container.querySelector('.semi-select') as HTMLElement);
    await userEvent.click(await screen.findByText('李四 · 前端组'));
    await waitFor(() => expect(onChange).toHaveBeenCalledWith(['u-lisi']));
  });
});
