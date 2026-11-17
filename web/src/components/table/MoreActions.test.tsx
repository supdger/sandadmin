import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { MoreActions } from './MoreActions';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('MoreActions', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('keeps the first two actions inline and folds the rest into a dropdown', async () => {
    const onAudit = vi.fn();
    render(
      <MoreActions
        actions={[
          { key: 'a', label: '查看', onClick: () => {} },
          { key: 'b', label: '编辑', onClick: () => {} },
          { key: 'c', label: '审计', onClick: onAudit },
        ]}
      />,
      { wrapper: wrap },
    );
    expect(await screen.findByText('查看')).toBeInTheDocument();
    expect(screen.getByText('编辑')).toBeInTheDocument();
    expect(screen.queryByText('审计')).not.toBeInTheDocument();

    await userEvent.click(screen.getByLabelText('更多'));
    await userEvent.click(await screen.findByText('审计'));
    expect(onAudit).toHaveBeenCalledOnce();
  });

  it('hides actions without permission', async () => {
    setToken('mock-viewer-token');
    render(
      <MoreActions
        actions={[
          { key: 'a', label: '查看', onClick: () => {} },
          { key: 'b', label: '删库', permission: 'system:god:mode', onClick: () => {} },
        ]}
      />,
      { wrapper: wrap },
    );
    expect(await screen.findByText('查看')).toBeInTheDocument();
    expect(screen.queryByText('删库')).not.toBeInTheDocument();
  });

  it('confirms before running a danger action', async () => {
    const onDelete = vi.fn();
    render(
      <MoreActions
        actions={[{ key: 'del', label: '删除', danger: true, onClick: onDelete }]}
      />,
      { wrapper: wrap },
    );
    await userEvent.click(await screen.findByText('删除'));
    expect(onDelete).not.toHaveBeenCalled();
    expect(await screen.findByText('确定执行该操作吗？')).toBeInTheDocument();

    const dialogs = screen.getAllByRole('dialog');
    const okButton = within(dialogs[dialogs.length - 1])
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('确定'));
    await userEvent.click(okButton as HTMLElement);
    await waitFor(() => expect(onDelete).toHaveBeenCalledOnce());
  });

  it('does nothing for a disabled action', async () => {
    const onClick = vi.fn();
    render(
      <MoreActions actions={[{ key: 'a', label: '停用', disabled: true, onClick }]} />,
      { wrapper: wrap },
    );
    const button = await screen.findByText('停用');
    expect(button.closest('button')).toBeDisabled();
  });
});
