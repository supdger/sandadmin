import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { TableToolbar } from './TableToolbar';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('TableToolbar', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('renders title, create button and fires callbacks', async () => {
    const onCreate = vi.fn();
    const onRefresh = vi.fn();
    render(
      <TableToolbar
        title="用户列表"
        create={{ permission: 'system:user:create', onClick: onCreate }}
        onRefresh={onRefresh}
      />,
      { wrapper: wrap },
    );
    expect(await screen.findByText('用户列表')).toBeInTheDocument();
    await userEvent.click(await screen.findByText('新建'));
    expect(onCreate).toHaveBeenCalledOnce();
    await userEvent.click(screen.getByLabelText('刷新'));
    expect(onRefresh).toHaveBeenCalledOnce();
  });

  it('hides the create button when permission is missing', async () => {
    setToken('mock-viewer-token');
    render(
      <TableToolbar create={{ permission: 'system:user:create', onClick: () => {} }} />,
      { wrapper: wrap },
    );
    await waitFor(() => expect(screen.queryByText('新建')).not.toBeInTheDocument());
  });

  it('disables batch actions without selection and confirms danger actions', async () => {
    const onDelete = vi.fn();
    const { rerender } = render(
      <TableToolbar
        batchActions={[{ key: 'rm', label: '批量删除', danger: true, onClick: onDelete }]}
        selectedKeys={[]}
      />,
      { wrapper: wrap },
    );
    const button = await screen.findByText('批量删除');
    expect(button.closest('button')).toBeDisabled();

    rerender(
      <TableToolbar
        batchActions={[{ key: 'rm', label: '批量删除', danger: true, onClick: onDelete }]}
        selectedKeys={['1', '2']}
      />,
    );
    expect(await screen.findByText('已选 2 项')).toBeInTheDocument();
    await userEvent.click(screen.getByText('批量删除'));
    expect(await screen.findByText('确定执行该操作吗？')).toBeInTheDocument();
    // Semi 弹窗按钮可访问名是 confirm/cancel，按可见文字找。
    const dialogs = screen.getAllByRole('dialog');
    const modal = dialogs[dialogs.length - 1];
    const okButton = within(modal)
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('确定'));
    await userEvent.click(okButton as HTMLElement);
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith(['1', '2']));
  });
});
