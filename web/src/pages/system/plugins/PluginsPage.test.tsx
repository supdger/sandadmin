import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import type { LifecycleRequest } from '@/api/v1/lifecycle';
import { getLifecycleRequest } from '@/api/v1/lifecycle';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { mockPluginsOverview, resetLifecycleMock } from '@/mocks/handlers/lifecycle';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { PluginsPage } from './PluginsPage';

vi.mock('@/api/v1/lifecycle', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/api/v1/lifecycle')>();
  return { ...orig, getLifecycleRequest: vi.fn() };
});

const fetchRequest = vi.mocked(getLifecycleRequest);

function makeRequest(patch: Partial<LifecycleRequest>): LifecycleRequest {
  return {
    id: 'req-1',
    kind: 'plugin_install',
    target_code: 'stats',
    source: 'official',
    from_version: null,
    to_version: '2.1.0',
    purge_data: false,
    items: null,
    state: 'running',
    stage: '正在处理',
    stage_key: null,
    progress: 30,
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
        <PluginsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function cardOf(name: string): HTMLElement {
  const card = screen.getByTestId(
    `plugin-card-${{ 流程审批: 'flow', 微信互通: 'wechat', 数据看板: 'stats', 打卡签到: 'signin' }[name]}`,
  );
  return card;
}

describe('PluginsPage', () => {
  beforeEach(() => {
    resetV1Mock();
    resetLifecycleMock();
    setToken('mock-admin-token');
  });
  afterEach(() => {
    clearToken();
    fetchRequest.mockReset();
  });

  it('按市场列表渲染卡片并区分安装态', async () => {
    renderPage();
    expect(await screen.findByText('插件管理')).toBeInTheDocument();
    expect(await screen.findByText('给后台加功能，或者升级、卸载已有的功能')).toBeInTheDocument();
    expect(await screen.findByText('流程审批')).toBeInTheDocument();
    expect(screen.getByText('微信互通')).toBeInTheDocument();
    expect(screen.getByText('数据看板')).toBeInTheDocument();
    // 「数据看板」未安装：卡片上是「安装」按钮
    expect(within(cardOf('数据看板')).getByRole('button', { name: '安装' })).toBeInTheDocument();
    // 「流程审批」已安装且有新版本：卡片上是「升级」按钮 + 有新版本标记
    expect(within(cardOf('流程审批')).getByRole('button', { name: '升级' })).toBeInTheDocument();
    expect(within(cardOf('流程审批')).getByText('有新版本')).toBeInTheDocument();
    // 头部「全部升级（1）」
    expect(screen.getByRole('button', { name: /全部升级（1）/ })).toBeInTheDocument();
  });

  it('点安装 → 受理后轮询 → 完成提示带名字', async () => {
    fetchRequest
      .mockResolvedValueOnce(makeRequest({ state: 'running', progress: 40 }))
      .mockResolvedValue(makeRequest({ state: 'succeeded', progress: 100 }));
    renderPage();

    await screen.findByText('数据看板');
    await userEvent.click(within(cardOf('数据看板')).getByRole('button', { name: '安装' }));

    // 进行中横幅出现（自己的申请在跑）
    expect(await screen.findByText(/「数据看板」正在安装/)).toBeInTheDocument();
    // 其他按钮在忙碌时被禁按
    expect(within(cardOf('打卡签到')).getByRole('button', { name: '安装' })).toBeDisabled();

    // 终态提示带返回的名字
    expect(
      await screen.findByText(/已安装「数据看板」/, {}, { timeout: 8000 }),
    ).toBeInTheDocument();
  });

  it('别人的申请在跑时整页互斥，只显示提示条', async () => {
    // 已有进行中申请（flow 升级中）：市场列表带 task_state，无 request_id 可看进度。
    server.use(
      http.get('/api/admin/v1/plugins/market', () =>
        HttpResponse.json({
          request_id: 'x',
          data: {
            items: [
              {
                id: 'plugin-flow', code: 'flow', name: '流程审批', version: '1.2.0',
                state: 'installed', task_state: 'running', rolled_back: false,
                signature_verified: true, description: null, provides: null,
                install_time: null, latest_version: '1.4.0', installed: true, upgrade_available: true,
              },
              {
                id: 'plugin-stats', code: 'stats', name: '数据看板', version: '2.1.0',
                state: 'uninstalled', task_state: null, rolled_back: false,
                signature_verified: true, description: null, provides: null,
                install_time: null, latest_version: '2.1.0', installed: false, upgrade_available: false,
              },
            ],
          },
        }),
      ),
    );
    renderPage();

    await screen.findByText('数据看板');
    // 顶部互斥提示条 + 其他卡片按钮禁按
    expect(await screen.findByText(/「流程审批」还没做完/)).toBeInTheDocument();
    await waitFor(() =>
      expect(within(cardOf('数据看板')).getByRole('button', { name: '安装' })).toBeDisabled(),
    );
    expect(within(cardOf('流程审批')).getByText('执行中')).toBeInTheDocument();
  });

  it('编译版只读：副标题不提升级卸载，卡片没有操作按钮', async () => {
    mockPluginsOverview.compiled = true;
    renderPage();
    expect(await screen.findByText('查看已安装的插件和版本，有新版本时提醒你')).toBeInTheDocument();
    expect(await screen.findByText(/不能在网页上安装或升级插件/)).toBeInTheDocument();
    expect(screen.queryByText('给后台加功能，或者升级、卸载已有的功能')).not.toBeInTheDocument();
    await screen.findByText('数据看板');
    expect(within(cardOf('数据看板')).queryByRole('button', { name: '安装' })).not.toBeInTheDocument();
    expect(within(cardOf('流程审批')).queryByRole('button', { name: '升级' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /全部升级/ })).not.toBeInTheDocument();
  });

  it('详情抽屉显示版本与菜单清单', async () => {
    renderPage();
    await screen.findByText('流程审批');
    await userEvent.click(screen.getByText('流程审批'));

    expect(await screen.findByText('插件详情')).toBeInTheDocument();
    expect(await screen.findByText(/审批中心/)).toBeInTheDocument();
    // 抽屉里有卸载入口（更多按钮）
    expect(await screen.findByLabelText('更多')).toBeInTheDocument();
  });
});
