import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { StatsCard } from './StatsCard';

describe('StatsCard', () => {
  it('渲染标题、数值与单位', () => {
    render(<StatsCard title="注册用户" value={1280} unit="人" animated={false} />);
    expect(screen.getByText('注册用户')).toBeInTheDocument();
    expect(screen.getByText('1280')).toBeInTheDocument();
    expect(screen.getByText('人')).toBeInTheDocument();
  });

  it('正环比渲染上升箭头与百分比', () => {
    render(<StatsCard title="销量" value={10} changePercent={12} animated={false} />);
    expect(screen.getByLabelText('上升')).toBeInTheDocument();
    expect(screen.getByText(/12%/)).toBeInTheDocument();
    expect(screen.getByText(/较上期/)).toBeInTheDocument();
  });

  it('负环比渲染下降箭头与自定义说明', () => {
    render(
      <StatsCard title="销量" value={10} changePercent={-8} changeLabel="较昨日" animated={false} />,
    );
    expect(screen.getByLabelText('下降')).toBeInTheDocument();
    expect(screen.getByText(/8%/)).toBeInTheDocument();
    expect(screen.getByText(/较昨日/)).toBeInTheDocument();
  });

  it('不传环比时不渲染环比行', () => {
    render(<StatsCard title="销量" value={10} animated={false} />);
    expect(screen.queryByText(/较上期/)).toBeNull();
  });
});
