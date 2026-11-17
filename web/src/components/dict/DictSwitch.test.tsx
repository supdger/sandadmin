import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { DictSwitch } from './DictSwitch';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

function theSwitch() {
  return screen.getByRole('switch');
}

describe('DictSwitch', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('maps the switch to the first two dict values by default', async () => {
    const onChange = vi.fn();
    render(<DictSwitch code="user_state" value="v_item-st-2" onChange={onChange} />, {
      wrapper: wrap,
    });
    await waitFor(() => expect(theSwitch()).not.toBeDisabled());
    expect(theSwitch()).not.toBeChecked();

    await userEvent.click(theSwitch());
    // 打开 = 字典第 1 项（正常）。
    expect(onChange).toHaveBeenCalledWith('v_item-st-1');
  });

  it('honors explicit on/off values', async () => {
    const onChange = vi.fn();
    render(<DictSwitch value={2} onValue={1} offValue={2} onChange={onChange} />, {
      wrapper: wrap,
    });
    expect(theSwitch()).not.toBeChecked();
    await userEvent.click(theSwitch());
    expect(onChange).toHaveBeenCalledWith(1);
  });

  it('works as a plain boolean switch without a dict code', async () => {
    const onChange = vi.fn();
    render(<DictSwitch value={false} onChange={onChange} />, { wrapper: wrap });
    await userEvent.click(theSwitch());
    expect(onChange).toHaveBeenCalledWith(true);
  });
});
