import { beforeEach, describe, expect, it } from 'vitest';

import { setToken } from '@/lib/authToken';
import { fetchDashboardChart } from './dashboardChart';

describe('fetchDashboardChart', () => {
  beforeEach(() => setToken('mock-admin-token'));

  it('把 dashboard/charts 的单张图转成 CategoryChart', async () => {
    const chart = await fetchDashboardChart('login_trend', '7d');
    expect(chart.categories).toHaveLength(7);
    expect(chart.series).toHaveLength(1);
    expect(chart.series[0].name).toBe('login_trend');
    expect(chart.series[0].data).toHaveLength(7);
    for (const point of chart.series[0].data) {
      expect(typeof point).toBe('number');
    }
  });

  it('key 查不到时返回空图', async () => {
    const chart = await fetchDashboardChart('no_such_chart', '7d');
    expect(chart).toEqual({ categories: [], series: [] });
  });
});
