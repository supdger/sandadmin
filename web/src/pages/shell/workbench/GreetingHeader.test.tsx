import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { applySiteSettings, resetSiteSettings } from '@/lib/siteSettings';
import { GreetingHeader } from './GreetingHeader';

// jsdom has no ResizeObserver; Semi's observer component needs a no-op stub.
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}

function renderHeader() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <GreetingHeader />
    </QueryClientProvider>,
  );
}

describe('GreetingHeader', () => {
  beforeEach(() => {
    // 2026-11-12 23:30 UTC：UTC+14 已经是 11-13 下午，UTC-8 还是 11-12 下午。
    vi.useFakeTimers({ shouldAdvanceTime: true });
    vi.setSystemTime(new Date('2026-11-12T23:30:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
    clearToken();
    resetSiteSettings();
  });

  it('问候语与「今天」按浏览器时区，不随系统设置时区变', async () => {
    setToken('mock-admin-token');
    // 系统设置成 UTC+14（此刻已是 11-13）：日期仍按浏览器本地算。
    applySiteSettings({ timezone: 'Pacific/Kiritimati' });
    renderHeader();

    const local = new Date();
    expect(await screen.findByText(/好，管理员/)).toBeInTheDocument();
    expect(
      screen.getByText(new RegExp(`今天是 ${local.getMonth() + 1}月${local.getDate()}日`)),
    ).toBeInTheDocument();
  });
});
