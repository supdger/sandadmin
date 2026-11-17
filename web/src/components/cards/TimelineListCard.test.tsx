import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { TimelineListCard } from './TimelineListCard';

const ENTRIES = [
  {
    key: '1',
    time: '2026-01-05 10:00',
    title: '张三 创建了订单',
    description: '订单号 A-100',
    tone: 'success' as const,
  },
  { key: '2', title: '系统 执行了备份' },
];

describe('TimelineListCard', () => {
  it('渲染时间、标题与描述', () => {
    render(<TimelineListCard title="动态" entries={ENTRIES} />);
    expect(screen.getByText('2026-01-05 10:00')).toBeInTheDocument();
    expect(screen.getByText('张三 创建了订单')).toBeInTheDocument();
    expect(screen.getByText('订单号 A-100')).toBeInTheDocument();
    expect(screen.getByText('系统 执行了备份')).toBeInTheDocument();
  });

  it('空列表渲染空态文案', () => {
    render(<TimelineListCard title="动态" entries={[]} />);
    expect(screen.getByText('暂无数据')).toBeInTheDocument();
  });
});
