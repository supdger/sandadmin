import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { ProgressCard } from './ProgressCard';

describe('ProgressCard', () => {
  it('渲染进度条与百分比', () => {
    const { container } = render(<ProgressCard title="存储用量" percent={60} />);
    expect(container.querySelector('.semi-progress')).not.toBeNull();
    expect(screen.getByText('60%')).toBeInTheDocument();
  });

  it('越界百分比收敛到 0–100', () => {
    const { container } = render(<ProgressCard title="用量" percent={140} />);
    const track = container.querySelector('.semi-progress-track');
    expect(track).not.toBeNull();
    expect(screen.getByText('100%')).toBeInTheDocument();
  });

  it('渲染描述文案', () => {
    render(<ProgressCard title="用量" percent={30} description="已用 3G / 共 10G" />);
    expect(screen.getByText('已用 3G / 共 10G')).toBeInTheDocument();
  });
});
