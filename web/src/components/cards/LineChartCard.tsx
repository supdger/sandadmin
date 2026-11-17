import type { CSSProperties, ReactNode } from 'react';

import { Card } from '@douyinfe/semi-ui';

import { LineChart } from '@/components/charts/LineChart';
import type { CategoryChart } from '@/components/charts/types';
import { ChartCardBody } from './ChartCardBody';
import { useCardQuery } from './useCardQuery';

export interface LineChartCardProps {
  title: ReactNode;
  /** 头部右侧操作位。 */
  extra?: ReactNode;
  /** 静态数据；与 fetcher 同时给时以它为准、不发请求。 */
  data?: CategoryChart;
  /** 无静态数据时的取数函数；用了就必须给 queryKey 区分缓存。 */
  fetcher?: () => Promise<CategoryChart>;
  queryKey?: readonly unknown[];
  smooth?: boolean;
  area?: boolean;
  showLegend?: boolean;
  height?: number | string;
  className?: string;
  style?: CSSProperties;
}

export function LineChartCard({
  title,
  extra,
  data,
  fetcher,
  queryKey,
  smooth,
  area,
  showLegend,
  height,
  className,
  style,
}: LineChartCardProps) {
  const query = useCardQuery<CategoryChart>({ data, fetcher, queryKey });
  const chart = data ?? query.data;
  const empty =
    !chart || chart.series.length === 0 || chart.categories.length === 0;
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
          <LineChart
            data={chart}
            smooth={smooth}
            area={area}
            showLegend={showLegend}
            height={height}
          />
        ) : null}
      </ChartCardBody>
    </Card>
  );
}
