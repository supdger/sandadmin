import '@douyinfe/semi-ui/react19-adapter';

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { ApiError } from '@/lib/apiClient';
import { ErrorState } from './ErrorState';
import { CopyProblemInfoButton, SystemErrorNotice } from './SystemErrorNotice';

const systemError = new ApiError({
  code: -1,
  message: '系统出了点问题，请稍后再试',
  system: true,
  method: 'GET',
  url: '/api/me/preferences',
  status: 500,
  requestId: 'req-123',
});

const businessError = new ApiError({ code: 4002, message: '用户名或密码不正确' });

describe('SystemErrorNotice', () => {
  it('shows the unified message, copy action and retry for system errors', () => {
    render(<SystemErrorNotice error={systemError} onRetry={() => undefined} />);

    expect(screen.getByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '重试' })).toBeInTheDocument();
  });

  it('renders the fallback for business errors', () => {
    render(
      <SystemErrorNotice error={businessError} fallback={<div>业务错误占位</div>} />,
    );

    expect(screen.getByText('业务错误占位')).toBeInTheDocument();
    expect(screen.queryByText('系统出了点问题，请稍后再试')).not.toBeInTheDocument();
  });
});

describe('CopyProblemInfoButton', () => {
  it('copies a multi-line report and confirms with a toast', async () => {
    const user = userEvent.setup();
    render(<CopyProblemInfoButton error={systemError} />);

    await user.click(screen.getByRole('button', { name: '复制问题信息' }));

    // user-event installs a working clipboard stub on setup(); read it back.
    await waitFor(async () => {
      const text = await navigator.clipboard.readText();
      expect(text).toContain('请求路径');
    });
    const text = await navigator.clipboard.readText();
    expect(text).toContain('/api/me/preferences');
    expect(text).toContain('GET');
    expect(text).toContain('请求编号');
    expect(text).toContain('req-123');
    expect(text).toContain(window.location.href);

    expect(await screen.findByText(/问题信息已复制/)).toBeInTheDocument();
  });
});

describe('ErrorState with an API error', () => {
  it('switches to the unified system message and offers copy for system errors', () => {
    render(
      <MemoryRouter>
        <ErrorState kind="error" error={systemError} onRetry={() => undefined} />
      </MemoryRouter>,
    );

    // Kind title stays, description becomes the unified system message.
    expect(screen.getByText('出了点问题')).toBeInTheDocument();
    expect(screen.getByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '重试' })).toBeInTheDocument();
  });

  it('keeps the generic copy and no copy action for business errors', () => {
    render(
      <MemoryRouter>
        <ErrorState kind="error" error={businessError} />
      </MemoryRouter>,
    );

    expect(screen.getByText('请稍后再试')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '复制问题信息' })).not.toBeInTheDocument();
  });
});
