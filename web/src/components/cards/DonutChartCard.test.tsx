import type { ReactNode } from 'react';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { RingChartProps } from '@/components/charts/RingChart';
import type { RingDatum } from '@/components/charts/types';

const captured = vi.hoisted(() => ({ props: [] as RingChartProps[] }));

vi.mock('@/components/charts/RingChart', () => ({
  RingChart: (props: RingChartProps) => {
    captured.props.push(props);
    return <div data-testid="ring-chart" />;
  },
}));

import '@/i18n/i18n';
import { DonutChartCard } from './DonutChartCard';

function wrap({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}

const SLICES: RingDatum[] = [
  { name: '甲', value: 4 },
  { name: '乙', value: 6 },
];

describe('DonutChartCard', () => {
  beforeEach(() => {
    captured.props.length = 0;
  });

  it('静态数据与 variant 透传给环图', () => {
    render(<DonutChartCard title="占比" data={SLICES} variant="pie" />, {
      wrapper: wrap,
    });
    const props = captured.props.at(-1);
    expect(props?.data).toBe(SLICES);
    expect(props?.variant).toBe('pie');
  });

  it('fetcher 形态取到数据后渲染', async () => {
    const fetcher = vi.fn().mockResolvedValue(SLICES);
    render(<DonutChartCard title="远程" fetcher={fetcher} queryKey={['donut']} />, {
      wrapper: wrap,
    });
    await waitFor(() => expect(captured.props.at(-1)?.data).toBe(SLICES));
  });
});
