import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { LineChartProps } from '@/components/charts/LineChart';
import type { CategoryChart } from '@/components/charts/types';

const captured = vi.hoisted(() => ({ props: [] as LineChartProps[] }));

vi.mock('@/components/charts/LineChart', () => ({
  LineChart: (props: LineChartProps) => {
    captured.props.push(props);
    return <div data-testid="line-chart" />;
  },
}));

import '@/i18n/i18n';
import { LineChartCard } from './LineChartCard';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const STATIC_CHART: CategoryChart = {
  categories: ['一'],
  series: [{ name: '趋势', data: [2] }],
};

describe('LineChartCard', () => {
  beforeEach(() => {
    captured.props.length = 0;
  });

  it('静态数据与 area/smooth 透传给图表', () => {
    render(<LineChartCard title="趋势" data={STATIC_CHART} smooth area />, {
      wrapper: wrap,
    });
    const props = captured.props.at(-1);
    expect(props?.data).toBe(STATIC_CHART);
    expect(props?.smooth).toBe(true);
    expect(props?.area).toBe(true);
  });

  it('fetcher 形态取到数据后渲染', async () => {
    const fetcher = vi.fn().mockResolvedValue(STATIC_CHART);
    render(<LineChartCard title="远程" fetcher={fetcher} queryKey={['line']} />, {
      wrapper: wrap,
    });
    await waitFor(() => expect(captured.props.at(-1)?.data).toBe(STATIC_CHART));
    expect(screen.getByText('远程')).toBeInTheDocument();
  });
});
