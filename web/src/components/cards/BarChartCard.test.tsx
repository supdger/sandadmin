import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { BarChartProps } from '@/components/charts/BarChart';
import type { CategoryChart } from '@/components/charts/types';

const captured = vi.hoisted(() => ({ props: [] as BarChartProps[] }));

vi.mock('@/components/charts/BarChart', () => ({
  BarChart: (props: BarChartProps) => {
    captured.props.push(props);
    return <div data-testid="bar-chart" />;
  },
}));

import '@/i18n/i18n';
import { BarChartCard } from './BarChartCard';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const STATIC_CHART: CategoryChart = {
  categories: ['一月'],
  series: [{ name: '销量', data: [3] }],
};

describe('BarChartCard', () => {
  beforeEach(() => {
    captured.props.length = 0;
  });

  it('静态数据直接渲染图表', () => {
    render(
      <BarChartCard title="月度销量" data={STATIC_CHART} stacked extra={<span>操作</span>} />,
      { wrapper: wrap },
    );
    expect(screen.getByText('月度销量')).toBeInTheDocument();
    expect(screen.getByText('操作')).toBeInTheDocument();
    expect(captured.props.at(-1)?.data).toBe(STATIC_CHART);
    expect(captured.props.at(-1)?.stacked).toBe(true);
  });

  it('fetcher 取到数据后渲染图表', async () => {
    const fetched: CategoryChart = {
      categories: ['一'],
      series: [{ name: '远程', data: [9] }],
    };
    const fetcher = vi.fn().mockResolvedValue(fetched);
    render(<BarChartCard title="远程图" fetcher={fetcher} queryKey={['remote']} />, {
      wrapper: wrap,
    });
    await waitFor(() => expect(captured.props.at(-1)?.data).toBe(fetched));
    expect(fetcher).toHaveBeenCalledTimes(1);
  });

  it('fetcher 失败给错误文案和重试入口', async () => {
    const fetcher = vi
      .fn<() => Promise<CategoryChart>>()
      .mockRejectedValueOnce(new Error('接口炸了'))
      .mockResolvedValueOnce(STATIC_CHART);
    render(<BarChartCard title="远程图" fetcher={fetcher} queryKey={['fail']} />, {
      wrapper: wrap,
    });
    expect(await screen.findByText('接口炸了')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: '重试' }));
    await waitFor(() => expect(captured.props.at(-1)?.data).toBe(STATIC_CHART));
    expect(fetcher).toHaveBeenCalledTimes(2);
  });

  it('data 为空图时渲染空态而不是图表', () => {
    render(
      <BarChartCard title="空图" data={{ categories: [], series: [] }} />,
      { wrapper: wrap },
    );
    expect(screen.getByText('暂无数据')).toBeInTheDocument();
    expect(screen.queryByTestId('bar-chart')).toBeNull();
  });

  it('data 与 fetcher 同时给时以 data 为准、不发请求', () => {
    const fetcher = vi.fn();
    render(<BarChartCard title="优先" data={STATIC_CHART} fetcher={fetcher} />, {
      wrapper: wrap,
    });
    expect(captured.props.at(-1)?.data).toBe(STATIC_CHART);
    expect(fetcher).not.toHaveBeenCalled();
  });
});
