import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { ConfigForm } from './ConfigForm';
import type { FormFieldConfig, SlotContext } from './FieldItem';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('FieldItem', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('hides a field marked hidden', async () => {
    const fields: FormFieldConfig[] = [
      { type: 'input', field: 'shown', label: '可见' },
      { type: 'input', field: 'ghost', label: '隐藏', hidden: true },
    ];
    render(<ConfigForm fields={fields} />, { wrapper: wrap });
    expect(await screen.findByText('可见')).toBeInTheDocument();
    expect(screen.queryByText('隐藏')).not.toBeInTheDocument();
  });

  it('renders dict-driven fields and submits the picked dict value', async () => {
    const onSubmit = vi.fn();
    const fields: FormFieldConfig[] = [
      { type: 'dictRadio', field: 'state', label: '状态', code: 'user_state' },
    ];
    render(<ConfigForm fields={fields} onSubmit={onSubmit} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('冻结'));
    await userEvent.click(screen.getByText('提交'));
    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ state: 'v_item-st-2' })),
    );
  });

  it('gives slot fields the form context for custom controls', async () => {
    const onSubmit = vi.fn();
    const fields: FormFieldConfig[] = [
      {
        type: 'slot',
        field: 'agree',
        label: '协议',
        render: (ctx: SlotContext) => (
          <button type="button" onClick={() => ctx.setValue(true)}>
            {ctx.value ? '已同意' : '点我同意'}
          </button>
        ),
      },
    ];
    render(<ConfigForm fields={fields} onSubmit={onSubmit} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('点我同意'));
    expect(await screen.findByText('已同意')).toBeInTheDocument();
    await userEvent.click(screen.getByText('提交'));
    await waitFor(() =>
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ agree: true })),
    );
  });
});
