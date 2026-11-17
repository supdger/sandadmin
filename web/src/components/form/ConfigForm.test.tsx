import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { AdminApiError } from '@/lib/adminClient';
import { setToken } from '@/lib/authToken';
import { ConfigForm } from './ConfigForm';
import type { FormFieldConfig } from './FieldItem';

const FIELDS: FormFieldConfig[] = [
  { type: 'input', field: 'name', label: '名称', required: true, placeholder: '请输入' },
  {
    type: 'select',
    field: 'level',
    label: '等级',
    options: [
      { label: '普通', value: 'normal' },
      { label: '高级', value: 'vip' },
    ],
  },
];

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('ConfigForm', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('blocks submit while a required field is empty', async () => {
    const onSubmit = vi.fn();
    render(<ConfigForm fields={FIELDS} onSubmit={onSubmit} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('提交'));
    expect(await screen.findByText('该项为必填')).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('emits values on successful submit', async () => {
    const onSubmit = vi.fn();
    render(<ConfigForm fields={FIELDS} onSubmit={onSubmit} />, { wrapper: wrap });
    await userEvent.type(screen.getByPlaceholderText('请输入'), '张三');
    await userEvent.click(screen.getByText('提交'));
    await waitFor(() => expect(onSubmit).toHaveBeenCalledWith({ name: '张三' }));
  });

  it('maps server fields errors onto the matching input', async () => {
    const error = new AdminApiError({
      code: 'VALIDATION_FAILED',
      message: '请检查表单内容',
      fields: { name: ['名称已被占用'] },
      httpStatus: 422,
      method: 'POST',
      url: '/api/admin/v1/test-rows',
    });
    render(<ConfigForm fields={FIELDS} error={error} />, { wrapper: wrap });
    expect(await screen.findByText('名称已被占用')).toBeInTheDocument();
  });

  it('resets values through the reset button', async () => {
    const onReset = vi.fn();
    render(
      <ConfigForm fields={FIELDS} initValues={{ name: '初始名' }} onReset={onReset} />,
      { wrapper: wrap },
    );
    const input = await screen.findByDisplayValue('初始名');
    await userEvent.type(input, '改');
    await userEvent.click(screen.getByText('重置'));
    // Semi reset 回到 initValues，不是清空。
    await waitFor(() => expect(input).toHaveValue('初始名'));
    expect(onReset).toHaveBeenCalledOnce();
  });
});
