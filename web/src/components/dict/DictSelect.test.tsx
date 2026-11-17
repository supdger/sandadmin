import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { DictSelect } from './DictSelect';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('DictSelect', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('loads dict options and emits the picked value', async () => {
    const onChange = vi.fn();
    const { container } = render(
      <DictSelect code="user_state" onChange={onChange} />,
      { wrapper: wrap },
    );
    // loading 期间点开的下拉换数据后选项不响应点击，先等加载完再开。
    await waitFor(() =>
      expect(container.querySelector('.semi-select .semi-spin')).toBeNull(),
    );
    await userEvent.click(container.querySelector('.semi-select') as HTMLElement);
    await userEvent.click(await screen.findByText('冻结'));
    expect(onChange).toHaveBeenCalledWith('v_item-st-2');
  });

  it('shows the controlled value label', async () => {
    render(<DictSelect code="user_state" value="v_item-st-1" />, { wrapper: wrap });
    expect(await screen.findByText('正常')).toBeInTheDocument();
  });

  it('supports multiple selection', async () => {
    const onChange = vi.fn();
    const { container } = render(
      <DictSelect code="user_state" multiple value={[]} onChange={onChange} />,
      { wrapper: wrap },
    );
    await waitFor(() =>
      expect(container.querySelector('.semi-select .semi-spin')).toBeNull(),
    );
    await userEvent.click(container.querySelector('.semi-select') as HTMLElement);
    await userEvent.click(await screen.findByText('已注销'));
    await waitFor(() => expect(onChange).toHaveBeenCalledWith(['v_item-st-3']));
  });
});
