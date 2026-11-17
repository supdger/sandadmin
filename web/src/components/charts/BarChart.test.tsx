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

import { BarChart } from './BarChart';

function lastOption() {
  return captured.props.at(-1)?.option as Record<string, unknown>;
}

describe('BarChart', () => {
  beforeEach(() => {
    captured.props.length = 0;
  });

  it('把类目和序列组装成 bar option', () => {
    render(
      <BarChart
        data={{ categories: ['一月', '二月'], series: [{ name: '销量', data: [3, 5] }] }}
      />,
    );
    const option = lastOption();
    expect(option.xAxis).toMatchObject({ type: 'category', data: ['一月', '二月'] });
    expect(option.yAxis).toMatchObject({ type: 'value' });
    expect(option.series).toEqual([
      expect.objectContaining({ type: 'bar', name: '销量', data: [3, 5] }),
    ]);
  });

  it('stacked 时序列带 stack', () => {
    render(
      <BarChart
        stacked
        data={{
          categories: ['一月'],
          series: [
            { name: '甲', data: [1] },
            { name: '乙', data: [2] },
          ],
        }}
      />,
    );
    const option = lastOption();
    for (const item of option.series as Array<{ stack?: string }>) {
      expect(item.stack).toBe('total');
    }
  });

  it('单序列默认不出图例，多序列自动出图例', () => {
    render(
      <BarChart data={{ categories: ['一'], series: [{ name: '单', data: [1] }] }} />,
    );
    expect(lastOption().legend).toBeUndefined();

    render(
      <BarChart
        data={{
          categories: ['一'],
          series: [
            { name: '甲', data: [1] },
            { name: '乙', data: [2] },
          ],
        }}
      />,
    );
    expect(lastOption().legend).toMatchObject({ data: ['甲', '乙'] });
  });

  it('loading 与自定义颜色透传', () => {
    render(
      <BarChart
        loading
        data={{
          categories: ['一'],
          series: [{ name: '单', data: [1], color: '#f00' }],
        }}
      />,
    );
    const props = captured.props.at(-1);
    expect(props?.loading).toBe(true);
    const option = lastOption();
    expect(option.series).toEqual([
      expect.objectContaining({ itemStyle: { color: '#f00' } }),
    ]);
  });
});
