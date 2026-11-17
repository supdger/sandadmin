import { getDashboardCharts, type ChartRange } from '@/api/v1/dashboard';
import type { CategoryChart } from '@/components/charts/types';

/**
 * 图表卡 fetcher 的示范：GET /dashboard/charts 的单张图
 * （key 如 login_trend）转成 CategoryChart，查不到给空图。
 */
export async function fetchDashboardChart(
  key: string,
  range: ChartRange = '30d',
): Promise<CategoryChart> {
  const result = await getDashboardCharts(range);
  const chart = result.charts.find((item) => item.key === key);
  if (!chart) {
    return { categories: [], series: [] };
  }
  return {
    categories: chart.points.map((point) => point.day),
    series: [{ name: chart.key, data: chart.points.map((point) => point.value) }],
  };
}
