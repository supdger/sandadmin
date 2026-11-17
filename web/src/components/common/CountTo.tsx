import { useEffect, useRef, useState } from 'react';

export interface CountToProps {
  /** 目标值；变化时从当前显示值续跑。 */
  value: number;
  /** 首帧起点，默认 0；只在首次渲染生效。 */
  from?: number;
  /** 动画时长毫秒；≤0 或 prefers-reduced-motion 时直出终值。 */
  duration?: number;
  decimals?: number;
  prefix?: string;
  suffix?: string;
  /** 千分位分隔符（如 ','），默认不加分组。 */
  separator?: string;
  /** 每次动画跑完（含直出）回调。 */
  onFinish?: () => void;
  className?: string;
}

function easeOutCubic(progress: number): number {
  return 1 - Math.pow(1 - progress, 3);
}

function reducedMotion(): boolean {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

function formatValue(value: number, decimals: number, separator: string): string {
  const fixed = value.toFixed(Math.max(0, decimals));
  if (!separator) {
    return fixed;
  }
  const [integer, fraction] = fixed.split('.');
  const sign = integer.startsWith('-') ? '-' : '';
  const digits = sign ? integer.slice(1) : integer;
  const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, separator);
  return `${sign}${grouped}${fraction ? `.${fraction}` : ''}`;
}

// jsdom 没实现 rAF 时用 setTimeout 兜底，动画仍按 duration 走完。
function nextFrame(callback: FrameRequestCallback): number {
  if (typeof requestAnimationFrame === 'function') {
    return requestAnimationFrame(callback);
  }
  return setTimeout(() => callback(performance.now()), 16) as unknown as number;
}

function dropFrame(id: number): void {
  if (typeof cancelAnimationFrame === 'function') {
    cancelAnimationFrame(id);
  } else {
    clearTimeout(id);
  }
}

/**
 * 数字滚动动效。value 变化即从当前显示值续跑到新目标；
 * 用户开了减少动态效果时直接显示终值，不播动画。
 */
export function CountTo({
  value,
  from = 0,
  duration = 1200,
  decimals = 0,
  prefix = '',
  suffix = '',
  separator = '',
  onFinish,
  className,
}: CountToProps) {
  const [display, setDisplay] = useState(from);
  const displayRef = useRef(display);
  const onFinishRef = useRef(onFinish);

  useEffect(() => {
    onFinishRef.current = onFinish;
  }, [onFinish]);

  useEffect(() => {
    const start = displayRef.current;
    if (duration <= 0 || reducedMotion() || start === value) {
      displayRef.current = value;
      setDisplay(value);
      onFinishRef.current?.();
      return;
    }
    const beganAt = performance.now();
    let frame = 0;
    const tick = (now: number) => {
      const progress = Math.min(1, (now - beganAt) / duration);
      const current = start + (value - start) * easeOutCubic(progress);
      displayRef.current = current;
      setDisplay(current);
      if (progress < 1) {
        frame = nextFrame(tick);
      } else {
        onFinishRef.current?.();
      }
    };
    frame = nextFrame(tick);
    return () => dropFrame(frame);
    // from 只管首帧，onFinish 走 ref；二者不进依赖。
  }, [value, duration]);

  return (
    <span className={className}>
      {prefix}
      {formatValue(display, decimals, separator)}
      {suffix}
    </span>
  );
}
