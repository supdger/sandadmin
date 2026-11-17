import { describe, expect, it } from 'vitest';

import { echarts } from './echarts';

describe('echarts base', () => {
  it('loads the core with registered charts and components', () => {
    expect(typeof echarts.init).toBe('function');
    expect(echarts.version).toBeTruthy();
  });
});
