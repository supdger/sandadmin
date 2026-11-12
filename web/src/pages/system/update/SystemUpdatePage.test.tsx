import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import type { LifecycleRequest } from '@/api/v1/lifecycle';
import { getLifecycleRequest } from '@/api/v1/lifecycle';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { resetLifecycleMock } from '@/mocks/handlers/lifecycle';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { SystemUpdatePage } from './SystemUpdatePage';

vi.mock('@/api/v1/lifecycle', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/api/v1/lifecycle')>();
  return { ...orig, getLifecycleRequest: vi.fn() };
});

const fetchRequest = vi.mocked(getLifecycleRequest);

function makeRequest(patch: Partial<LifecycleRequest>): LifecycleRequest {
  return {
    id: 'req-9',
    kind: 'system_upgrade',
    target_code: 'host',
    source: 'official',
    from_version: '1.4.2',
    to_version: '1.6.0',
    purge_data: false,
    items: null,
    state: 'running',
    stage: '正在下载',
    stage_key: 'download',
    progress: 60,
    rolled_back: false,
    error_code: null,
    error_message: null,
    create_time: '2026-06-10T10:00:00+08:00',
    start_time: '2026-06-10T10:00:01+08:00',
    finish_time: null,
    ...patch,
  };
}

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <SystemUpdatePage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

const STATUS_URL = '/api/admin/v1/system/update/status';

describe('SystemUpdatePage', () => {
  beforeEach(() => {
    resetV1Mock();
    resetLifecycleMock();
    setToken('mock-admin-token');
  });
  afterEach(() => {
    clearToken();
    fetchRequest.mockReset();
  });

  it('显示版本卡：现在的版本、可升级版本、变化与随行插件', async () => {
    renderPage();
    expect(await screen.findByText('现在的版本 1.4.2')).toBeInTheDocument();
    expect(await screen.findByText('可以升级到 1.6.0')).toBeInTheDocument();
    expect(screen.getByText('公告可以定时下线了')).toBeInTheDocument();
    expect(screen.getByText(/流程审批、微信互通/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '升级到 1.6.0' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '上传升级包' })).toBeInTheDocument();
  });

  it('点升级 → 五步进度 → 成功后提示刷新', async () => {
    fetchRequest
      .mockResolvedValueOnce(makeRequest({ stage_key: 'switch', stage: '正在切换', progress: 85 }))
      .mockResolvedValue(makeRequest({ state: 'succeeded', progress: 100 }));
    renderPage();

    await screen.findByRole('button', { name: '升级到 1.6.0' });
    await userEvent.click(screen.getByRole('button', { name: '升级到 1.6.0' }));
    expect(await screen.findByText('升级到 1.6.0？')).toBeInTheDocument();
    await userEvent.click(screen.getByText('升级'));

    // 进度视图：五阶段步骤条 + 执行中标签，没有取消按钮
    await screen.findByTestId('upgrade-progress');
    expect(screen.getByText('检查好了')).toBeInTheDocument();
    expect(screen.getByText('备份好了')).toBeInTheDocument();
    expect(screen.getByText('下载好了')).toBeInTheDocument();
    expect(screen.getAllByText('正在切换').length).toBeGreaterThan(0);
    expect(screen.getByText('正在检查结果')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /取消/ })).not.toBeInTheDocument();

    expect(await screen.findByText(/已升级到 1\.6\.0/, {}, { timeout: 8000 })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '刷新页面' })).toBeInTheDocument();
  });

  it('编译版只读：说明条 + 不显示操作按钮', async () => {
    server.use(
      http.get(STATUS_URL, () =>
        HttpResponse.json({
          request_id: 'x',
          data: {
            current_version: '1.4.2',
            latest_version: '1.6.0',
            update_available: true,
            changes: [],
            bundled_plugins: [],
            checked_time: null,
            compiled: true,
            package_ops_enabled: false,
            running_request: null,
          },
        }),
      ),
    );
    renderPage();
    expect(await screen.findByText(/这个后台不能在网页上升级/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /升级到/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '上传升级包' })).not.toBeInTheDocument();
  });

  it('申请已取消时显示「已取消，系统没有改动」', async () => {
    server.use(
      http.get(STATUS_URL, () =>
        HttpResponse.json({
          request_id: 'x',
          data: {
            current_version: '1.4.2',
            latest_version: '1.6.0',
            update_available: true,
            changes: [],
            bundled_plugins: [],
            checked_time: null,
            compiled: false,
            package_ops_enabled: true,
            running_request: makeRequest({ id: 'req-cancelled', state: 'cancelled' }),
          },
        }),
      ),
    );
    fetchRequest.mockResolvedValue(makeRequest({ state: 'cancelled' }));
    renderPage();
    // running_request 是终态 cancelled：不作为进行中恢复，但状态展示「已取消，系统没有改动」
    expect(await screen.findByText('已取消，系统没有改动')).toBeInTheDocument();
  });
});
