import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { NotificationsPage } from './NotificationsPage';

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

const base = `${env.apiBaseUrl}/admin/v1`;

function renderPage(entry = '/notifications') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[entry]}>
        <NotificationsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('NotificationsPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('lists inbox items with announcement tags and relative times', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText('消息')).toBeInTheDocument();
    expect(await screen.findByText('系统将于本周日凌晨 2:00 至 4:00 进行维护')).toBeInTheDocument();
    // Announcements carry the 公告 tag.
    expect(screen.getAllByText('公告').length).toBeGreaterThan(0);
    expect(screen.getByText('新版选项管理上线')).toBeInTheDocument();
  });

  it('filters to unread via the URL-backed tab', async () => {
    setToken('mock-admin-token');
    renderPage('/notifications?tab=unread');

    await screen.findByText('系统将于本周日凌晨 2:00 至 4:00 进行维护');
    // Every seeded item starts unread, so the unread tab still has rows.
    expect(screen.getByText('全部')).toBeInTheDocument();
    expect(screen.getByText('未读')).toBeInTheDocument();
  });

  it('marks a single item read when opened', async () => {
    setToken('mock-admin-token');
    let readCalls = 0;
    server.use(
      http.post(`${base}/notifications/ntf-001/read`, () => {
        readCalls += 1;
        return HttpResponse.json({ data: { read: true }, request_id: 't' });
      }),
    );
    renderPage();

    const item = await screen.findByText('你的密码已在另一台设备上修改，其余登录已退出');
    await userEvent.click(item);
    await waitFor(() => expect(readCalls).toBe(1));
  });

  it('shows the unread empty state on the unread tab after read-all', async () => {
    setToken('mock-admin-token');
    renderPage('/notifications?tab=unread');
    await screen.findByText('系统将于本周日凌晨 2:00 至 4:00 进行维护');

    await userEvent.click(screen.getByRole('button', { name: '全部标为已读' }));
    expect(await screen.findByText('消息都看过了')).toBeInTheDocument();
  });

  it('shows the error state when the list request fails', async () => {
    setToken('mock-admin-token');
    server.use(
      http.get(`${base}/notifications`, () =>
        HttpResponse.json(
          { code: 'INTERNAL', message: 'x', request_id: 'r' },
          { status: 500 },
        ),
      ),
    );
    renderPage();

    expect(await screen.findByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
  });
});
