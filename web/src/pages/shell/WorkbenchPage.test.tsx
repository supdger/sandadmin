import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { WorkbenchPage } from './WorkbenchPage';

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

describe('WorkbenchPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('greets the signed-in user by nickname and shows placeholder cards', async () => {
    setToken('t-1');
    server.use(
      http.get(`${base}/me`, () =>
        HttpResponse.json({
          code: 200,
          message: 'ok',
          data: {
            id: 'u-1',
            username: 'admin',
            nickname: '管理员',
            mustChangePassword: false,
            permissions: [],
          },
        }),
      ),
    );

    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    });
    render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <WorkbenchPage />
        </MemoryRouter>
      </QueryClientProvider>,
    );

    expect(await screen.findByText(/好，管理员/)).toBeInTheDocument();
    expect(screen.getByText('最近访问')).toBeInTheDocument();
    expect(screen.getByText('系统公告')).toBeInTheDocument();
    expect(screen.getByText('待办事项')).toBeInTheDocument();
  });
});
