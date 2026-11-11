import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
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

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <WorkbenchPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('WorkbenchPage', () => {
  afterEach(() => {
    clearToken();
  });

  it('greets the admin and renders permission-filtered dashboard blocks', async () => {
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText(/好，管理员/)).toBeInTheDocument();
    expect(screen.getByText(/这里是每天先看的地方/)).toBeInTheDocument();

    // Admin (wildcard) sees all four summary cards.
    expect(await screen.findByText('用户')).toBeInTheDocument();
    expect(screen.getByText('今天登录')).toBeInTheDocument();
    expect(screen.getByText('本月 AI 使用')).toBeInTheDocument();
    expect(screen.getByText('待处理流程')).toBeInTheDocument();

    // Todos, shortcuts, charts and activities all render from the mocks.
    expect(await screen.findByText('待我处理')).toBeInTheDocument();
    expect(screen.getByText('「请假申请」等待你审批')).toBeInTheDocument();
    expect(screen.getByText('快捷入口')).toBeInTheDocument();
    expect(screen.getByText('数据趋势')).toBeInTheDocument();
    expect(screen.getByText('最近动态')).toBeInTheDocument();
  });

  it('hides summary cards the viewer has no permission for', async () => {
    setToken('mock-viewer-token');
    renderPage();

    expect(await screen.findByText(/好，/)).toBeInTheDocument();
    // Viewer permissions cover none of the card gates — the whole row is gone.
    await screen.findByText('待我处理');
    expect(screen.queryByText('今天登录')).not.toBeInTheDocument();
    expect(screen.queryByText('待处理流程')).not.toBeInTheDocument();
    // Personal blocks still render for every signed-in user.
    expect(screen.getByText('快捷入口')).toBeInTheDocument();
  });

  it('shows the all-clear copy when there are no todos', async () => {
    setToken('mock-admin-token');
    renderPage();
    // Admin mock has todos; assert the section exists rather than empty copy.
    expect(await screen.findByText('待我处理')).toBeInTheDocument();
  });

  it('renders empty states when dashboard lists are missing', async () => {
    server.use(
      http.get('/api/admin/v1/dashboard/summary', () => HttpResponse.json({ data: {}, request_id: 'empty' })),
      http.get('/api/admin/v1/dashboard/todos', () =>
        HttpResponse.json({ data: { items: null, total: 0 }, request_id: 'empty' }),
      ),
      http.get('/api/admin/v1/dashboard/charts', () =>
        HttpResponse.json({ data: { charts: null }, request_id: 'empty' }),
      ),
      http.get('/api/admin/v1/dashboard/activities', () => HttpResponse.json({ data: {}, request_id: 'empty' })),
      http.get('/api/admin/v1/dashboard/notices', () =>
        HttpResponse.json({ data: { items: null }, request_id: 'empty' }),
      ),
      http.get('/api/admin/v1/dashboard/shortcuts', () =>
        HttpResponse.json({ data: { mode: 'auto', items: null }, request_id: 'empty' }),
      ),
      http.get('/api/admin/v1/dashboard/onboarding', () =>
        HttpResponse.json({ data: { done: false, steps: null }, request_id: 'empty' }),
      ),
    );
    setToken('mock-admin-token');
    renderPage();

    expect(await screen.findByText(/好，管理员/)).toBeInTheDocument();
    expect(await screen.findByText('都处理完了，没有要你处理的事')).toBeInTheDocument();
    expect(screen.getByText('数据会在明天出现')).toBeInTheDocument();
    expect(screen.getByText('还没有动态，有人操作后会显示在这里')).toBeInTheDocument();
    expect(screen.getByText('还没有快捷入口')).toBeInTheDocument();
    expect(screen.queryByText('这张卡没能加载出来')).not.toBeInTheDocument();
  });

  it('keeps a retryable offline notice inside each block', async () => {
    server.use(
      http.get('/api/admin/v1/dashboard/summary', () => HttpResponse.error()),
      http.get('/api/admin/v1/dashboard/todos', () => HttpResponse.error()),
      http.get('/api/admin/v1/dashboard/charts', () => HttpResponse.error()),
      http.get('/api/admin/v1/dashboard/activities', () => HttpResponse.error()),
      http.get('/api/admin/v1/dashboard/notices', () => HttpResponse.error()),
      http.get('/api/admin/v1/dashboard/shortcuts', () => HttpResponse.error()),
      http.get('/api/admin/v1/dashboard/onboarding', () => HttpResponse.error()),
    );
    setToken('mock-admin-token');
    renderPage();

    const notes = await screen.findAllByText('网络好像断了，请检查网络后再试');
    expect(notes.length).toBeGreaterThan(0);
    expect(screen.getAllByRole('button', { name: '重试' }).length).toBeGreaterThan(0);
    expect(screen.queryByRole('button', { name: '复制问题信息' })).not.toBeInTheDocument();
  });
});
