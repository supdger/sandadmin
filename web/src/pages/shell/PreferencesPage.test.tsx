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
    document.body.removeAttribute('theme-mode');
  });

  it('saves the theme through PUT /me/preferences and applies it at once', async () => {
    setToken('t-1');
    let putBody: Record<string, unknown> | null = null;
    server.use(
      http.put(`${base}/me/preferences`, async ({ request }) => {
        putBody = (await request.json()) as Record<string, unknown>;
        return HttpResponse.json({ code: 200, message: 'ok', data: null });
      }),
    );

    renderPage();

    // Wait for the loaded preferences to render the radio group.
    expect(await screen.findByText('主题模式')).toBeInTheDocument();
    expect(screen.getByLabelText('跟随系统')).toBeChecked();

    await userEvent.click(screen.getByLabelText('深色'));

    await waitFor(() => {
      expect(putBody).not.toBeNull();
      expect(putBody).toMatchObject({ theme: 'dark' });
    });
    expect(document.body.getAttribute('theme-mode')).toBe('dark');
    expect(await screen.findByText('偏好已保存', {}, { timeout: 3000 })).toBeInTheDocument();
  });

  it('saves the notification switch through the same endpoint', async () => {
    setToken('t-1');
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
    setToken('t-1');
    server.use(
      http.get(`${base}/me/preferences`, () =>
        HttpResponse.json({ code: 5000, message: '服务开了小差', data: null }),
      ),
    );

    renderPage();

    expect(await screen.findByText('出了点问题')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '重试' })).toBeInTheDocument();
  });
});
