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
import { mockUpdateStatus, resetLifecycleMock } from '@/mocks/handlers/lifecycle';
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
    expect(await screen.findByText('一键升级后台和所有已安装的插件')).toBeInTheDocument();
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

    // 点升级不弹确认，直接开始走进度
    await screen.findByRole('button', { name: '升级到 1.6.0' });
    await userEvent.click(screen.getByRole('button', { name: '升级到 1.6.0' }));

    // 进度视图：五阶段步骤条 + 执行中标签，没有取消按钮
    await screen.findByTestId('upgrade-progress');
    expect(screen.getByText('环境检查好了')).toBeInTheDocument();
    expect(screen.getByText('数据备份好了')).toBeInTheDocument();
    expect(screen.getByText('新版本下载好了')).toBeInTheDocument();
    expect(screen.getAllByText('正在切换到新版本').length).toBeGreaterThan(0);
    expect(screen.getByText('确认能正常用')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /取消/ })).not.toBeInTheDocument();

    expect(await screen.findByText(/已升级到 1\.6\.0/, {}, { timeout: 8000 })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '刷新页面' })).toBeInTheDocument();
  });

  it('非超管看「有新版本」写联系部署的人，不写「可以升级」', async () => {
    server.use(
      http.get('/api/admin/v1/me', () =>
        HttpResponse.json({
          request_id: 'x',
          data: {
            id: 'u-ops',
            username: 'ops',
            nickname: '运维同学',
            avatar: null,
            avatar_file_id: null,
            phone: '',
            email: null,
            status: 'enabled',
            is_admin: true,
            is_super: false,
            must_change_password: false,
            permissions: ['system:update:index'],
            dept_id: 'dept-root',
            dept_name: '沙盒网络',
            post_id: null,
            post_name: null,
            role_ids: [],
            role_names: [],
            last_login_time: null,
            create_time: '2026-01-01T00:00:00+08:00',
          },
        }),
      ),
    );
    renderPage();
    expect(await screen.findByText('有新版本 1.6.0，请联系负责部署的人升级')).toBeInTheDocument();
    expect(screen.queryByText(/可以升级到/)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /升级到/ })).not.toBeInTheDocument();
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
    expect(await screen.findByText(/有新版本 1\.6\.0。这台服务器不支持在网页里升级/)).toBeInTheDocument();
    expect(screen.getByText('查看当前版本，有新版本时提醒你')).toBeInTheDocument();
    expect(screen.queryByText('一键升级后台和所有已安装的插件')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /升级到/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '上传升级包' })).not.toBeInTheDocument();
  });

  it('不能网页升级时失败已恢复：原因单独一行，没有再试一次', async () => {
    server.use(
      http.get(STATUS_URL, () =>
        HttpResponse.json({
          request_id: 'x',
          data: {
            ...mockUpdateStatus(),
            compiled: true,
            package_ops_enabled: false,
            running_request: null,
            last_failed_request: makeRequest({
              state: 'failed',
              rolled_back: true,
              error_message: '下载新版本时网络断了',
            }),
          },
        }),
      ),
    );
    renderPage();
    expect(await screen.findByText('上次升级没做完，已自动恢复')).toBeInTheDocument();
    expect(screen.getByText('原因：下载新版本时网络断了')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '再试一次' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();
  });

  it('不能网页升级时失败未恢复：没有再试一次，恢复与复制仍在', async () => {
    server.use(
      http.get(STATUS_URL, () =>
        HttpResponse.json({
          request_id: 'x',
          data: {
            ...mockUpdateStatus(),
            compiled: true,
            package_ops_enabled: false,
            running_request: null,
            last_failed_request: makeRequest({
              state: 'failed',
              rolled_back: false,
              error_message: '下载新版本时网络断了',
            }),
          },
        }),
      ),
    );
    renderPage();
    expect(await screen.findByText('升级没有完成')).toBeInTheDocument();
    expect(screen.getByText('原因：下载新版本时网络断了')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: '再试一次' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: '恢复到原来的版本' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();
  });

  it('网页能升级时失败页仍给「再试一次」', async () => {
    server.use(
      http.get(STATUS_URL, () =>
        HttpResponse.json({
          request_id: 'x',
          data: {
            ...mockUpdateStatus(),
            running_request: null,
            last_failed_request: makeRequest({
              state: 'failed',
              rolled_back: true,
              error_message: '下载新版本时网络断了',
            }),
          },
        }),
      ),
    );
    renderPage();
    expect(await screen.findByText('上次升级没做完，已自动恢复')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '再试一次' })).toBeInTheDocument();
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
