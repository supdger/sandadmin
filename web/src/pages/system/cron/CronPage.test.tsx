import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { resetV1Mock } from '@/mocks/handlers/_v1data';
import { CronPage } from './CronPage';
import { nextRunTimes, scheduleText } from './scheduleText';

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <CronPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('scheduleText', () => {
  it('把结构化时间翻译成人话', () => {
    expect(scheduleText({ freq: 'daily', time: '03:00' })).toBe('每天 03:00');
    expect(scheduleText({ freq: 'weekly', weekday: 1, time: '09:00' })).toBe('每周一 09:00');
    expect(scheduleText({ freq: 'interval', interval: 10 })).toBe('每 10 分钟');
    expect(scheduleText({ freq: 'monthly', monthday: 31, time: '06:00' })).toBe('每月 31 号 06:00');
    expect(scheduleText(null)).toBe('时间由系统定');
  });

  it('monthly 31 号遇短月落到月末', () => {
    const runs = nextRunTimes(
      { freq: 'monthly', monthday: 31, time: '06:00' },
      new Date('2026-01-15T00:00:00+08:00'),
    );
    expect(runs.map((d) => d.getDate())).toEqual([31, 28, 31]);
  });
});

describe('CronPage', () => {
  beforeEach(() => {
    resetV1Mock();
    setToken('mock-admin-token');
  });
  afterEach(() => clearToken());

  it('表格显示人话时间，不出现 cron 表达式', async () => {
    renderPage();
    expect(await screen.findByText('每天 03:00')).toBeInTheDocument();
    expect(screen.getByText('每周一 09:00')).toBeInTheDocument();
    expect(screen.getByText('每 10 分钟')).toBeInTheDocument();
    expect(screen.queryByText(/\*\/\d|cron|0 \* \*/i)).not.toBeInTheDocument();
  });

  it('失败横幅只在大于零时出现并给「只看失败的」', async () => {
    renderPage();
    expect(await screen.findByText('有 1 个任务上次没有成功')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '只看失败的' })).toBeInTheDocument();
  });

  it('锁定任务不出现可点的「改时间」', async () => {
    renderPage();
    await screen.findByText('系统自检');
    // 锁定行的改时间按钮是禁用的。
    const editButtons = screen.getAllByRole('button', { name: '改时间' });
    expect(editButtons.some((b) => (b as HTMLButtonElement).disabled)).toBe(true);
  });

  it('执行记录抽屉显示运行历史', async () => {
    renderPage();
    const runsButtons = await screen.findAllByRole('button', { name: '执行记录' });
    await userEvent.click(runsButtons[0]);
    expect(await screen.findByText('「数据备份」的执行记录')).toBeInTheDocument();
    expect(screen.getByText(/手动执行/)).toBeInTheDocument();
    expect(screen.getByText('系统出了点问题，请稍后再试')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: '复制问题信息' })).toBeInTheDocument();
  });
});
