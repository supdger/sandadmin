import { useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';

import { useTranslation } from 'react-i18next';

import { echarts, type ECharts, type ChartOption } from '@/lib/echarts';
import { useThemeMode } from '@/layouts/themeMode';

export interface EChartProps {
  /** echarts option；变化时整量 setOption（不合并，避免残留旧系列）。 */
  option: ChartOption;
  /** 容器高度；默认 320px。宽度跟随容器并由 ResizeObserver 自适应。 */
  height?: number | string;
  loading?: boolean;
  /** 拿到 chart 实例的逃生口（绑定事件、导出图片等）。 */
  onReady?: (chart: ECharts) => void;
  className?: string;
  style?: CSSProperties;
}

/**
 * echarts React 封装基座：初始化、option 更新、加载态、容器
 * resize 自适应、明暗主题跟随都在这里，图表组件只传 option。
 * 暗色切换会销毁重建（echarts 不支持运行期换主题）。
 */
export function EChart({ option, height = 320, loading, onReady, className, style }: EChartProps) {
  const { t } = useTranslation('feChart');
  const { isDark } = useThemeMode();
  const containerRef = useRef<HTMLDivElement | null>(null);
  const chartRef = useRef<ECharts | null>(null);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) {
      return;
    }
    const chart = echarts.init(container, isDark ? 'dark' : undefined);
    chartRef.current = chart;
    onReady?.(chart);
    setReady(true);

    const observer = new ResizeObserver(() => {
      chart.resize();
    });
    observer.observe(container);

    return () => {
      observer.disconnect();
      chart.dispose();
      chartRef.current = null;
      setReady(false);
    };
    // onReady 不触发重建：它是回调不是数据。
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isDark]);

  useEffect(() => {
    if (ready && chartRef.current) {
      chartRef.current.setOption(option, { notMerge: true });
    }
  }, [option, ready]);

  useEffect(() => {
    const chart = chartRef.current;
    if (!ready || !chart) {
      return;
    }
    if (loading) {
      chart.showLoading('default', { text: t('loading'), maskColor: 'transparent' });
    } else {
      chart.hideLoading();
    }
  }, [loading, ready, t]);

  return (
    <div
      ref={containerRef}
      className={className}
      style={{ width: '100%', height, ...style }}
    />
  );
}
