import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { __authMock } from '@/mocks/handlers/auth';
import { PasswordChangeModal } from './PasswordChangeModal';

if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}
if (typeof Range !== 'undefined' && !Range.prototype.getBoundingClientRect) {
  Range.prototype.getBoundingClientRect = () =>
    ({ width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0, x: 0, y: 0 }) as DOMRect;
}

function renderModal(onClose = vi.fn()) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  render(
    <QueryClientProvider client={queryClient}>
      <PasswordChangeModal visible onClose={onClose} />
    </QueryClientProvider>,
  );
  return onClose;
}

describe('PasswordChangeModal', () => {
  afterEach(() => {
    clearToken();
    // The mock revokes rotated tokens; restore the seed tokens per case.
    __authMock.reset();
  });

  it('shows the admin-tier policy hints and submits a valid change', async () => {
    setToken('mock-admin-token');
    const onClose = renderModal();

    // Admin policy: min 10 chars.
    expect(await screen.findByText('至少 10 位')).toBeInTheDocument();

    await userEvent.type(screen.getByPlaceholderText('请输入当前密码'), 'Admin@123456');
    await userEvent.type(screen.getByPlaceholderText('请输入新密码'), 'NewPassw0rd!');
    await userEvent.type(screen.getByPlaceholderText('请再输入一次新密码'), 'NewPassw0rd!');
    await userEvent.click(screen.getByRole('button', { name: '修改密码' }));

    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });

  it('maps the backend old-password error onto the form field', async () => {
    setToken('mock-admin-token');
    renderModal();
    await screen.findByText('至少 10 位');

    await userEvent.type(screen.getByPlaceholderText('请输入当前密码'), 'wrong-password');
    await userEvent.type(screen.getByPlaceholderText('请输入新密码'), 'NewPassw0rd!');
    await userEvent.type(screen.getByPlaceholderText('请再输入一次新密码'), 'NewPassw0rd!');
    await userEvent.click(screen.getByRole('button', { name: '修改密码' }));

    expect(
      await screen.findByText('当前密码不对，请再输一遍'),
    ).toBeInTheDocument();
  });

  it('rejects mismatched confirmation client-side', async () => {
    setToken('mock-admin-token');
    renderModal();
    await screen.findByText('至少 10 位');

    await userEvent.type(screen.getByPlaceholderText('请输入当前密码'), 'Admin@123456');
    await userEvent.type(screen.getByPlaceholderText('请输入新密码'), 'NewPassw0rd!');
    await userEvent.type(screen.getByPlaceholderText('请再输入一次新密码'), 'Different1!');
    await userEvent.click(screen.getByRole('button', { name: '修改密码' }));

    expect(
      await screen.findByText('两次输入的密码不一样，请再输一遍'),
    ).toBeInTheDocument();
  });
});
