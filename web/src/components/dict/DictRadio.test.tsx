import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { DictRadio } from './DictRadio';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('DictRadio', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('emits the picked dict value', async () => {
    const onChange = vi.fn();
    render(<DictRadio code="user_state" onChange={onChange} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('已注销'));
    expect(onChange).toHaveBeenCalledWith('v_item-st-3');
  });

  it('reflects the controlled value', async () => {
    render(<DictRadio code="user_state" value="v_item-st-2" />, { wrapper: wrap });
    const frozen = await screen.findByText('冻结');
    expect(frozen.closest('label')?.querySelector('input')).toBeChecked();
  });
});
