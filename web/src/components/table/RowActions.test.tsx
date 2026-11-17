import '@douyinfe/semi-ui/react19-adapter';
import '@/i18n/i18n';

import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setToken } from '@/lib/authToken';
import { RowActions } from './RowActions';

interface Row {
  id: string;
  name: string;
}

const ROW: Row = { id: 'r-1', name: '样例行' };

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

describe('RowActions', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('passes the record to edit/delete handlers', async () => {
    const onEdit = vi.fn();
    render(<RowActions record={ROW} onEdit={onEdit} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('编辑'));
    expect(onEdit).toHaveBeenCalledWith(ROW);
  });

  it('confirms the delete action before invoking the handler', async () => {
    const onDelete = vi.fn();
    render(<RowActions record={ROW} onDelete={onDelete} />, { wrapper: wrap });
    await userEvent.click(await screen.findByText('删除'));
    expect(await screen.findByText('确定执行该操作吗？')).toBeInTheDocument();
    expect(onDelete).not.toHaveBeenCalled();

    const dialogs = screen.getAllByRole('dialog');
    const okButton = within(dialogs[dialogs.length - 1])
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('确定'));
    await userEvent.click(okButton as HTMLElement);
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith(ROW));
  });

  it('hides edit/delete when the user lacks the permission', async () => {
    setToken('mock-viewer-token');
    render(
      <RowActions
        record={ROW}
        onEdit={() => {}}
        editPermission="system:user:update"
        onDelete={() => {}}
        deletePermission="system:user:delete"
      />,
      { wrapper: wrap },
    );
    // 等 useMe 取到权限后再断言；两行操作都不可见时组件返回 null。
    await waitFor(() => {
      expect(screen.queryByText('编辑')).not.toBeInTheDocument();
      expect(screen.queryByText('删除')).not.toBeInTheDocument();
    });
  });

  it('supports extra custom actions', async () => {
    const onReset = vi.fn();
    render(
      <RowActions
        record={ROW}
        actions={[{ key: 'reset', label: '重置密码', onClick: onReset }]}
      />,
      { wrapper: wrap },
    );
    await userEvent.click(await screen.findByText('重置密码'));
    expect(onReset).toHaveBeenCalledWith(ROW);
  });
});
