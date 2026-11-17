import { useMemo } from 'react';
import type { CSSProperties } from 'react';

import { EChart, type EChartProps } from '@/components/EChart';
import type { ChartOption } from '@/lib/echarts';
import type { RingDatum } from './types';

export interface RingChartProps {
  data: RingDatum[];
  /** 'ring' 中空环图（默认）；'pie' 实心饼图。 */
  variant?: 'ring' | 'pie';
  /** echarts radius 内外径；缺省环图 ['50%','70%']、饼图 ['0%','70%']。 */
  radius?: [string, string];
  showLegend?: boolean;
  height?: number | string;
  loading?: boolean;
  onReady?: EChartProps['onReady'];
  className?: string;
  style?: CSSProperties;
}

export function RingChart({
  data,
  variant = 'ring',
  radius,
  showLegend,
  height,
  loading,
  onReady,
  className,
  style,
}: RingChartProps) {
  const legend = showLegend ?? true;
  const option = useMemo<ChartOption>(
    () => ({
      tooltip: { trigger: 'item' },
      legend: legend ? { bottom: 0 } : undefined,
      series: [
        {
          type: 'pie',
          radius: radius ?? (variant === 'pie' ? ['0%', '70%'] : ['50%', '70%']),
          center: ['50%', '45%'],
          label: { show: variant === 'pie' },
          data: data.map((item) => ({
            name: item.name,
            value: item.value,
            itemStyle: item.color ? { color: item.color } : undefined,
          })),
        },
      ],
    }),
    [data, variant, radius, legend],
  );
  return (
    <EChart
      option={option}
      height={height}
      loading={loading}
      onReady={onReady}
      className={className}
      style={style}
    />
  );
}
