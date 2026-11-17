import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { EChartProps } from '@/components/EChart';

const captured = vi.hoisted(() => ({ props: [] as EChartProps[] }));

// echarts 画布在 jsdom 跑不起来，换成记录 props 的桩，断言组装的 option。
vi.mock('@/components/EChart', () => ({
  EChart: (props: EChartProps) => {
    captured.props.push(props);
    return <div data-testid="echart" />;
  },
}));

import { RingChart } from './RingChart';

function lastSeries() {
  const option = captured.props.at(-1)?.option as Record<string, unknown>;
  return (option.series as Array<Record<string, unknown>>)[0];
}

describe('RingChart', () => {
  beforeEach(() => {
    captured.props.length = 0;
  });

  it('默认渲染中空环图', () => {
    render(<RingChart data={[{ name: '甲', value: 3 }]} />);
    const series = lastSeries();
    expect(series).toMatchObject({ type: 'pie', radius: ['50%', '70%'] });
    expect(series.data).toEqual([expect.objectContaining({ name: '甲', value: 3 })]);
  });

  it('pie 变体是实心饼图且显示标签', () => {
    render(<RingChart variant="pie" data={[{ name: '甲', value: 3 }]} />);
    const series = lastSeries();
    expect(series.radius).toEqual(['0%', '70%']);
    expect(series.label).toMatchObject({ show: true });
  });

  it('radius 可覆盖，扇区颜色透传', () => {
    render(
      <RingChart
        radius={['40%', '60%']}
        data={[{ name: '甲', value: 3, color: '#0af' }]}
      />,
    );
    const series = lastSeries();
    expect(series.radius).toEqual(['40%', '60%']);
    expect(series.data).toEqual([
      expect.objectContaining({ itemStyle: { color: '#0af' } }),
    ]);
  });
});
