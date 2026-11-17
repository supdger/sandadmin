import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import '@/i18n/i18n';
import { DataListCard } from './DataListCard';

const ROWS = [
  { key: '1', title: '任务一', description: '两小时前', extra: <span>紧急</span> },
  { key: '2', title: '任务二' },
  { key: '3', title: '任务三' },
];

describe('DataListCard', () => {
  it('渲染行标题、描述与右侧位', () => {
    render(<DataListCard title="待办" rows={ROWS} />);
    expect(screen.getByText('任务一')).toBeInTheDocument();
    expect(screen.getByText('两小时前')).toBeInTheDocument();
    expect(screen.getByText('紧急')).toBeInTheDocument();
  });

  it('onMore 渲染「查看全部」并响应点击', async () => {
    const onMore = vi.fn();
    render(<DataListCard title="待办" rows={ROWS} onMore={onMore} />);
    await userEvent.click(screen.getByRole('button', { name: '查看全部' }));
    expect(onMore).toHaveBeenCalledTimes(1);
  });

  it('maxRows 截断展示行数', () => {
    render(<DataListCard title="待办" rows={ROWS} maxRows={2} />);
    expect(screen.getByText('任务二')).toBeInTheDocument();
    expect(screen.queryByText('任务三')).toBeNull();
  });

  it('空列表渲染空态文案', () => {
    render(<DataListCard title="待办" rows={[]} />);
    expect(screen.getByText('暂无数据')).toBeInTheDocument();
  });
});
