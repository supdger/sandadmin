import '@douyinfe/semi-ui/react19-adapter';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { clearToken, setToken } from '@/lib/authToken';
import { zonePartsAt } from '@/lib/zonedTime';
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

  it('按设置时区换算成浏览器时区：北京 03:00 → 洛杉矶 11:00', () => {
    // 每天 03:00 北京时间，浏览器在洛杉矶：当天 11:00（前一天 19:00 的那次在昨天）。
    expect(
      scheduleText({ freq: 'daily', time: '03:00' }, 'Asia/Shanghai', 'America/Los_Angeles'),
    ).toBe('每天 11:00');
    // 与 next_run_time 的浏览器读出同钟点：北京 03:00 的瞬间在洛杉矶是 11:00。
    const runs = nextRunTimes(
      { freq: 'daily', time: '03:00' },
      new Date('2026-01-10T00:00:00+08:00'),
      'Asia/Shanghai',
    );
    const at = zonePartsAt(runs[0], 'America/Los_Angeles');
    expect(`${String(at.hour).padStart(2, '0')}:${String(at.minute).padStart(2, '0')}`).toBe('11:00');
  });

  it('weekly 跨界：周一 03:00 北京 → 周日 11:00 洛杉矶', () => {
    expect(
      scheduleText(
        { freq: 'weekly', weekday: 1, time: '03:00' },
        'Asia/Shanghai',
        'America/Los_Angeles',
      ),
    ).toBe('每周日 11:00');
  });

  it('monthly 跨日：2 号 06:00 北京 → 1 号 14:00 洛杉矶', () => {
    expect(
      scheduleText(
        { freq: 'monthly', monthday: 2, time: '06:00' },
        'Asia/Shanghai',
        'America/Los_Angeles',
      ),
    ).toBe('每月 1 号 14:00');
  });

  it('interval 不做时区换算', () => {
    expect(
      scheduleText({ freq: 'interval', interval: 10 }, 'Asia/Shanghai', 'America/Los_Angeles'),
    ).toBe('每 10 分钟');
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

  it('锁定任务不出现「改时间」', async () => {
    renderPage();
    const name = await screen.findByText('系统自检');
    // schedule_locked=清单锁定：该行完全不渲染改时间按钮。
    const row = name.closest('tr');
    expect(row).not.toBeNull();
    expect(within(row as HTMLElement).queryByRole('button', { name: '改时间' })).toBeNull();
    // 未锁定的任务照常出现。
    expect(screen.getAllByRole('button', { name: '改时间' }).length).toBeGreaterThan(0);
  });

  it('状态筛选有「已启用」选项', async () => {
    const { container } = renderPage();
    await screen.findByText('每天 03:00');
    // Semi Select 点开才渲染 optionList；默认显示「全部」。
    await userEvent.click(container.querySelector('.semi-select') as HTMLElement);
    expect(await screen.findByText('已启用')).toBeInTheDocument();
  });

  it('改时间弹窗里字段非法时不渲染「接下来三次」预览', async () => {
    renderPage();
    // 「统计汇总」是 interval 任务：把间隔改到 99（超上限）。
    const name = await screen.findByText('统计汇总');
    const row = name.closest('tr') as HTMLElement;
    await userEvent.click(within(row).getByRole('button', { name: '改时间' }));
    expect(await screen.findByText('接下来三次会在')).toBeInTheDocument();
    const input = screen.getByRole('spinbutton');
    await userEvent.clear(input);
    await userEvent.type(input, '99');
    expect(screen.queryByText('接下来三次会在')).not.toBeInTheDocument();
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
