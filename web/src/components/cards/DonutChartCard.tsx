import type { CSSProperties, ReactNode } from 'react';

import { Card } from '@douyinfe/semi-ui';

import { RingChart } from '@/components/charts/RingChart';
import type { RingDatum } from '@/components/charts/types';
import { ChartCardBody } from './ChartCardBody';
import { useCardQuery } from './useCardQuery';

export interface DonutChartCardProps {
  title: ReactNode;
  /** 头部右侧操作位。 */
  extra?: ReactNode;
  /** 静态数据；与 fetcher 同时给时以它为准、不发请求。 */
  data?: RingDatum[];
  /** 无静态数据时的取数函数；用了就必须给 queryKey 区分缓存。 */
  fetcher?: () => Promise<RingDatum[]>;
  queryKey?: readonly unknown[];
  variant?: 'ring' | 'pie';
  radius?: [string, string];
  showLegend?: boolean;
  height?: number | string;
  className?: string;
  style?: CSSProperties;
}

export function DonutChartCard({
  title,
  extra,
  data,
  fetcher,
  queryKey,
  variant,
  radius,
  showLegend,
  height,
  className,
  style,
}: DonutChartCardProps) {
  const query = useCardQuery<RingDatum[]>({ data, fetcher, queryKey });
  const chart = data ?? query.data;
  const empty = !chart || chart.length === 0;
  return (
    <Card title={title} headerExtraContent={extra} className={className} style={style}>
      <ChartCardBody
        pending={query.pending}
        error={query.error}
        onRetry={query.retry}
        empty={empty}
        height={height}
      >
        {chart ? (
          <RingChart
            data={chart}
            variant={variant}
            radius={radius}
            showLegend={showLegend}
            height={height}
          />
        ) : null}
      </ChartCardBody>
    </Card>
  );
}
