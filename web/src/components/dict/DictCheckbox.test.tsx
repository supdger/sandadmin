import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { DictCheckbox } from './DictCheckbox';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('DictCheckbox', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('renders enabled dict options and emits the value array', async () => {
    const onChange = vi.fn();
    render(<DictCheckbox code="user_state" value={[]} onChange={onChange} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('冻结'));
    expect(onChange).toHaveBeenCalledWith(['v_item-st-2']);
  });

  it('keeps controlled checked state', async () => {
    render(<DictCheckbox code="user_state" value={['v_item-st-1', 'v_item-st-3']} />, {
      wrapper: wrap,
    });
    expect(await screen.findByRole('checkbox', { name: '正常' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: '冻结' })).not.toBeChecked();
  });
});
