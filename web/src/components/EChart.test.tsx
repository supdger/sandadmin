import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const fake = vi.hoisted(() => ({
  setOption: vi.fn(),
  resize: vi.fn(),
  showLoading: vi.fn(),
  hideLoading: vi.fn(),
  dispose: vi.fn(),
  init: vi.fn(),
}));

vi.mock('@/lib/echarts', () => ({
  echarts: { init: fake.init },
}));

import '@/i18n/i18n';
import { EChart } from './EChart';

describe('EChart', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    fake.init.mockReturnValue(fake);
  });

  it('inits the chart, applies the option and disposes on unmount', () => {
    const option = { series: [{ type: 'bar', data: [1, 2] }] };
    const { unmount } = render(<EChart option={option} />);
    expect(fake.init).toHaveBeenCalledTimes(1);
    expect(fake.setOption).toHaveBeenCalledWith(option, { notMerge: true });
    unmount();
    expect(fake.dispose).toHaveBeenCalledTimes(1);
  });

  it('shows and hides loading around the loading prop', () => {
    const { rerender } = render(<EChart option={{}} loading />);
    expect(fake.showLoading).toHaveBeenCalledTimes(1);
    rerender(<EChart option={{}} loading={false} />);
    expect(fake.hideLoading).toHaveBeenCalled();
  });

  it('inits with the dark theme when dark mode is on', () => {
    document.body.setAttribute('theme-mode', 'dark');
    render(<EChart option={{}} />);
    expect(fake.init).toHaveBeenCalledWith(expect.anything(), 'dark');
    document.body.removeAttribute('theme-mode');
  });
});
