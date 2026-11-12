import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { server } from '@/mocks/server';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { v1Base } from '@/mocks/handlers/_v1';
import { MonitorPage } from './MonitorPage';

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <MonitorPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('MonitorPage', () => {
  beforeEach(() => {
    resetV1Mock();
    setToken('mock-admin-token');
  });
  afterEach(() => clearToken());

  it('渲染四张状态卡与结论', async () => {
    renderPage();
    expect(await screen.findByText('处理器忙碌度')).toBeInTheDocument();
    expect(screen.getByText('内存使用')).toBeInTheDocument();
    expect(screen.getByText('磁盘空间')).toBeInTheDocument();
    expect(screen.getByText('数据库连接')).toBeInTheDocument();
    expect(screen.getByText('一切正常')).toBeInTheDocument();
    // 不出现 CPU/IP 等技术词。
    expect(screen.queryByText(/CPU|IP|端口|进程/)).not.toBeInTheDocument();
  });

  it('暂停后按钮变成继续更新', async () => {
    renderPage();
    const pause = await screen.findByText('暂停更新');
    await userEvent.click(pause);
    expect(await screen.findByText('继续更新')).toBeInTheDocument();
    expect(screen.getByText('已暂停，数字不会变')).toBeInTheDocument();
  });

  it('数据库连不上时出危险结论与下一步', async () => {
    server.use(
      http.get(`${v1Base}/monitor/server`, () =>
        HttpResponse.json({
          data: {
            collected_time: '2026-01-10T08:00:00+08:00',
            db_ok: false,
            db_checked_time: '2026-01-10T08:00:00+08:00',
            cpu: { usage_percent: 10 },
            memory: { usage_percent: 30, used_mb: 100, total_mb: 512 },
            disk: { free_gb: 40, total_gb: 100 },
          },
          request_id: 'req-1',
        }),
      ),
    );
    renderPage();
    expect(await screen.findAllByText('连不上')).not.toHaveLength(0);
    expect(screen.getByText('数据库连不上，请尽快处理')).toBeInTheDocument();
  });
});
