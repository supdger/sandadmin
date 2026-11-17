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

import { LineChart } from './LineChart';

function lastOption() {
  return captured.props.at(-1)?.option as Record<string, unknown>;
}

describe('LineChart', () => {
  beforeEach(() => {
    captured.props.length = 0;
  });

  it('把类目和序列组装成 line option', () => {
    render(
      <LineChart
        data={{ categories: ['一月', '二月'], series: [{ name: '访问量', data: [3, 5] }] }}
      />,
    );
    const option = lastOption();
    expect(option.xAxis).toMatchObject({
      type: 'category',
      data: ['一月', '二月'],
      boundaryGap: false,
    });
    expect(option.series).toEqual([
      expect.objectContaining({ type: 'line', name: '访问量', data: [3, 5] }),
    ]);
  });

  it('smooth 与 area 开关落到序列上', () => {
    render(
      <LineChart
        smooth
        area
        data={{ categories: ['一'], series: [{ name: '趋势', data: [1] }] }}
      />,
    );
    expect(lastOption().series).toEqual([
      expect.objectContaining({ smooth: true, areaStyle: { opacity: 0.2 } }),
    ]);
  });

  it('不开 area 时序列没有 areaStyle', () => {
    render(
      <LineChart data={{ categories: ['一'], series: [{ name: '趋势', data: [1] }] }} />,
    );
    const item = (lastOption().series as Array<{ areaStyle?: unknown }>)[0];
    expect(item.areaStyle).toBeUndefined();
  });
});
