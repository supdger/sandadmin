import { act, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { CountTo } from './CountTo';

describe('CountTo', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('duration 为 0 时直接渲染终值与前后缀', () => {
    render(
      <CountTo value={1234.5} duration={0} decimals={1} separator="," prefix="¥" suffix="元" />,
    );
    expect(screen.getByText('¥1,234.5元')).toBeInTheDocument();
  });

  it('prefers-reduced-motion 时不播动画直出终值', () => {
    vi.spyOn(window, 'matchMedia').mockImplementation(
      (query: string) =>
        ({
          matches: query.includes('reduce'),
          media: query,
          onchange: null,
          addListener: () => {},
          removeListener: () => {},
          addEventListener: () => {},
          removeEventListener: () => {},
          dispatchEvent: () => false,
        }) as MediaQueryList,
    );
    render(<CountTo value={88} duration={2000} />);
    expect(screen.getByText('88')).toBeInTheDocument();
  });

  it('按帧推进并在跑完时回调 onFinish', () => {
    const frames: FrameRequestCallback[] = [];
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      frames.push(callback);
      return frames.length;
    });
    vi.stubGlobal('cancelAnimationFrame', () => {});
    const onFinish = vi.fn();
    render(<CountTo value={100} duration={1000} onFinish={onFinish} />);
    expect(screen.getByText('0')).toBeInTheDocument();
    // 一帧把时钟推到远超 duration，进度收敛到 1。
    act(() => {
      frames.shift()?.(performance.now() + 2000);
    });
    expect(screen.getByText('100')).toBeInTheDocument();
    expect(onFinish).toHaveBeenCalledTimes(1);
  });

  it('value 变化后从新帧续跑到新目标', () => {
    const frames: FrameRequestCallback[] = [];
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      frames.push(callback);
      return frames.length;
    });
    vi.stubGlobal('cancelAnimationFrame', () => {});
    const { rerender } = render(<CountTo value={50} duration={0} />);
    expect(screen.getByText('50')).toBeInTheDocument();
    rerender(<CountTo value={80} duration={1000} />);
    act(() => {
      frames.shift()?.(performance.now() + 2000);
    });
    expect(screen.getByText('80')).toBeInTheDocument();
  });
});
