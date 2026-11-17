import { useMemo } from 'react';
import type { CSSProperties } from 'react';

import { EChart, type EChartProps } from '@/components/EChart';
import type { ChartOption } from '@/lib/echarts';
import type { CategoryChart } from './types';

export interface LineChartProps {
  data: CategoryChart;
  smooth?: boolean;
  /** 线下填充为面积图。 */
  area?: boolean;
  /** 缺省时多序列自动显示、单序列隐藏。 */
  showLegend?: boolean;
  height?: number | string;
  loading?: boolean;
  onReady?: EChartProps['onReady'];
  className?: string;
  style?: CSSProperties;
}

export function LineChart({
  data,
  smooth,
  area,
  showLegend,
  height,
  loading,
  onReady,
  className,
  style,
}: LineChartProps) {
  const legend = showLegend ?? data.series.length > 1;
  const option = useMemo<ChartOption>(
    () => ({
      tooltip: { trigger: 'axis' },
      legend: legend ? { data: data.series.map((item) => item.name) } : undefined,
      grid: { left: 8, right: 8, top: legend ? 40 : 16, bottom: 8, containLabel: true },
      xAxis: { type: 'category', data: data.categories, boundaryGap: false },
      yAxis: { type: 'value' },
      series: data.series.map((item) => ({
        type: 'line',
        name: item.name,
        data: item.data,
        smooth,
        areaStyle: area ? { opacity: 0.2 } : undefined,
        itemStyle: item.color ? { color: item.color } : undefined,
      })),
    }),
    [data, smooth, area, legend],
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
