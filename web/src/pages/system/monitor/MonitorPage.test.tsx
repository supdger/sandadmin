import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

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
    // 只叠一条 Banner：结论句 + 下一步句 + 最近检查时间在一起。
    expect(screen.getByText('系统连不上数据库，请联系负责服务器的同事检查数据库是否在运行')).toBeInTheDocument();
  });

  it('有卡读不到且其余正常时，结论列出读不到的卡名', async () => {
    server.use(
      http.get(`${v1Base}/monitor/server`, () =>
        HttpResponse.json({
          data: {
            collected_time: '2026-01-10T08:00:00+08:00',
            db_ok: true,
            db_checked_time: '2026-01-10T08:00:00+08:00',
            cpu: { usage_percent: null },
            memory: { usage_percent: 30, used_mb: 100, total_mb: 512 },
            disk: { free_gb: 40, total_gb: 100 },
          },
          request_id: 'req-1',
        }),
      ),
    );
    renderPage();
    expect(
      await screen.findByText('能读到的都正常，处理器忙碌度暂时读不到'),
    ).toBeInTheDocument();
  });

  it('复制摘要成功提示「已复制，可以直接发给管理员」', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText },
      configurable: true,
    });
    renderPage();
    // 图标 aria-label 会并进可访问名（"copy 复制状态摘要"），用正则匹配。
    await userEvent.click(await screen.findByRole('button', { name: /复制状态摘要/ }));
    expect(await screen.findByText('已复制，可以直接发给管理员')).toBeInTheDocument();
    expect(writeText).toHaveBeenCalled();
    // 复制失败不冒充加载失败。
    writeText.mockRejectedValueOnce(new Error('denied'));
    await userEvent.click(screen.getByRole('button', { name: /复制状态摘要/ }));
    expect(await screen.findByText('没有复制成功，请再试一次')).toBeInTheDocument();
  });

  it('内存与磁盘容量用人话（GB/MB、共多少）', async () => {
    renderPage();
    // mock: used_mb 512 / total_mb 1248 → 512 MB，共 1.2 GB；磁盘 42/100 GB → 还剩 42 GB。
    expect(await screen.findByText('512 MB')).toBeInTheDocument();
    expect(screen.getByText('本系统占用 512 MB（这台服务器共 1.2 GB）')).toBeInTheDocument();
    expect(screen.getByText('还剩 42 GB')).toBeInTheDocument();
    expect(screen.getByText('这台服务器共 100 GB')).toBeInTheDocument();
  });
});
