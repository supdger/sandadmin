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
import { PreferencesPage } from './PreferencesPage';

// jsdom has no ResizeObserver; Semi's observer component needs a no-op stub.
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
}

// jsdom's Range lacks getBoundingClientRect; Semi's typography ellipsis
// measurement calls it in a floating promise.
if (typeof Range !== 'undefined' && !Range.prototype.getBoundingClientRect) {
  Range.prototype.getBoundingClientRect = () =>
    ({ width: 0, height: 0, top: 0, left: 0, right: 0, bottom: 0, x: 0, y: 0 }) as DOMRect;
}

const base = env.apiBaseUrl;

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <PreferencesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('PreferencesPage', () => {
  afterEach(() => {
    clearToken();
    localStorage.removeItem('sandadmin.theme');
    document.body.removeAttribute('theme-mode');
  });

  it('applies the theme locally without calling the save API', async () => {
    setToken('mock-admin-token');
    let putCalls = 0;
    server.use(
      http.put(`${base}/me/preferences`, () => {
        putCalls += 1;
        return HttpResponse.json({ code: 200, message: 'ok', data: null });
      }),
    );

    renderPage();

    // Wait for the loaded preferences to render the radio group.
    expect(await screen.findByText('主题模式')).toBeInTheDocument();
    expect(screen.getByLabelText('跟随系统')).toBeChecked();
    // Theme is a per-device setting — the hint says so next to the label.
    expect(screen.getByText('只在这台电脑上生效')).toBeInTheDocument();

    await userEvent.click(screen.getByLabelText('深色'));

    await waitFor(() => {
      expect(screen.getByLabelText('深色')).toBeChecked();
    });
    expect(document.body.getAttribute('theme-mode')).toBe('dark');
    expect(localStorage.getItem('sandadmin.theme')).toBe('dark');
    expect(putCalls).toBe(0);
  });

  it('saves the notification switch through the same endpoint', async () => {
    setToken('mock-admin-token');
    let putBody: Record<string, unknown> | null = null;
    server.use(
      http.put(`${base}/me/preferences`, async ({ request }) => {
        putBody = (await request.json()) as Record<string, unknown>;
        return HttpResponse.json({ code: 200, message: 'ok', data: null });
      }),
    );

    renderPage();
    await screen.findByText('主题模式');

    await userEvent.click(screen.getByLabelText('邮件通知'));

    await waitFor(() => {
      expect(putBody).toMatchObject({ emailNotify: true });
    });
  });

  it('shows an error state with retry when preferences fail to load', async () => {
    setToken('mock-admin-token');
    server.use(
      http.get(`${base}/me/preferences`, () =>
        HttpResponse.json({ code: 5000, message: '服务开了小差', data: null }),
      ),
    );

    renderPage();

    expect(await screen.findByText('出了点问题')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '重试' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '复制问题信息' })).not.toBeInTheDocument();
  });

  it('shows the unified system notice with a copy action on HTTP 5xx', async () => {
    setToken('mock-admin-token');
    server.use(
      http.get(`${base}/me/preferences`, () =>
        HttpResponse.json({ code: 5000, message: 'boom', data: null }, { status: 500 }),
      ),
    );

    renderPage();

    // Kind title stays; the description switches to the unified system copy.
    expect(await screen.findByText('出了点问题')).toBeInTheDocument();
    expect(screen.getByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '重试' })).toBeInTheDocument();
  });

  it('shows the backend reason verbatim when saving preferences fails', async () => {
    setToken('mock-admin-token');
    server.use(
      http.put(`${base}/me/preferences`, () =>
        HttpResponse.json({
          code: 4030,
          message: '今天系统维护，请明天下午再试',
          data: null,
        }),
      ),
    );

    renderPage();
    await screen.findByText('主题模式');

    await userEvent.click(screen.getByLabelText('邮件通知'));

    // The toast must carry the backend's plain-language message, not a
    // generic save-failed line.
    expect(await screen.findByText('今天系统维护，请明天下午再试')).toBeInTheDocument();
    expect(screen.queryByText('保存失败了，请稍后再试')).not.toBeInTheDocument();
  });

  it('keeps theme usable but disables writes for a read-only account', async () => {
    // mock-viewer-token resolves to the viewer demo user: view permissions
    // only, no preferences:write.
    setToken('mock-viewer-token');
    let putCalls = 0;
    server.use(
      http.put(`${base}/me/preferences`, () => {
        putCalls += 1;
        return HttpResponse.json({ code: 200, message: 'ok', data: null });
      }),
    );

    renderPage();

    expect(await screen.findByText('主题模式')).toBeInTheDocument();
    expect(
      screen.getByText('当前账号为只读权限，通知设置暂时不能改'),
    ).toBeInTheDocument();
    expect(screen.getByLabelText('邮件通知')).toBeDisabled();

    // Theme never leaves the device, so the read-only account can switch it.
    const dark = screen.getByLabelText('深色');
    expect(dark).toBeEnabled();
    await userEvent.click(dark);
    await waitFor(() => {
      expect(localStorage.getItem('sandadmin.theme')).toBe('dark');
    });
    expect(document.body.getAttribute('theme-mode')).toBe('dark');
    expect(putCalls).toBe(0);
  });
});
