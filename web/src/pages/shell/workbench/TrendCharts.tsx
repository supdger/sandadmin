import { Card, Empty, Skeleton, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import { getDashboardCharts, type ChartRange, type DashboardChart } from '@/api/v1/dashboard';

const RANGE_KEYS: ChartRange[] = ['7d', '30d', '90d'];
const CHARTS_QUERY_KEY = ['dashboard', 'charts'] as const;

const CHART_TITLES: Record<string, string> = {
  login_trend: 'charts.titles.login_trend',
  ai_usage: 'charts.titles.ai_usage',
};

function rangeFromParams(value: string | null): ChartRange {
  return value === '30d' || value === '90d' ? value : '7d';
}

/** Minimal dependency-free line chart: points scaled into an SVG viewBox. */
function LineChart({ chart }: { chart: DashboardChart }) {
  const width = 560;
  const height = 160;
  const padding = 8;
  const values = chart.points.map((p) => p.value);
  const max = Math.max(...values, 1);
  const stepX = chart.points.length > 1 ? (width - padding * 2) / (chart.points.length - 1) : 0;
  const coords = chart.points.map((p, i) => {
    const x = padding + i * stepX;
    const y = height - padding - (p.value / max) * (height - padding * 2);
    return `${x},${y}`;
  });
  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      className="h-40 w-full"
      role="img"
      aria-label={chart.key}
    >
      <polyline
        points={coords.join(' ')}
        fill="none"
        stroke="var(--semi-color-primary)"
        strokeWidth="2"
      />
      {chart.points.map((p, i) => {
        const [x, y] = coords[i].split(',').map(Number);
        return <circle key={p.day} cx={x} cy={y} r="3" fill="var(--semi-color-primary)" />;
      })}
    </svg>
  );
}

/** 数据趋势 card — range selector (7d/30d/90d) persisted in ?range=. */
export function TrendCharts() {
  const { t } = useTranslation('workbench');
  const [searchParams, setSearchParams] = useSearchParams();
  const range = rangeFromParams(searchParams.get('range'));

  const charts = useQuery({
    queryKey: [...CHARTS_QUERY_KEY, range],
    queryFn: () => getDashboardCharts(range),
  });

  const setRange = (next: ChartRange) => {
    setSearchParams(
      (prev) => {
        const params = new URLSearchParams(prev);
        if (next === '7d') {
          params.delete('range');
        } else {
          params.set('range', next);
        }
        return params;
      },
      { replace: true },
    );
  };

  const list = charts.data?.charts ?? [];

  return (
    <Card
      title={t('charts.title')}
      headerExtraContent={
        <div className="flex gap-1" role="group" aria-label={t('charts.title')}>
          {RANGE_KEYS.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => setRange(key)}
              className="rounded px-2 py-1 text-sm"
              style={{
                backgroundColor:
                  range === key ? 'var(--semi-color-primary)' : 'transparent',
                color:
                  range === key ? 'var(--semi-color-white)' : 'var(--semi-color-text-1)',
              }}
            >
              {t(`charts.range${key === '7d' ? '7' : key === '30d' ? '30' : '90'}`)}
            </button>
          ))}
        </div>
      }
    >
      {charts.isPending ? (
        <Skeleton loading placeholder={<Skeleton.Paragraph rows={4} />}>
          <div />
        </Skeleton>
      ) : charts.isError ? (
        <Typography.Text type="danger" size="small">
          {t('cards.error')}
        </Typography.Text>
      ) : list.length === 0 ? (
        <Empty title={t('charts.empty')} description={undefined} />
      ) : (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          {list.map((chart) => (
            <div key={chart.key}>
              <Typography.Text strong size="small">
                {CHART_TITLES[chart.key] ? t(CHART_TITLES[chart.key]) : chart.key}
              </Typography.Text>
              <LineChart chart={chart} />
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}
