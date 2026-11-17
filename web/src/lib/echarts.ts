import { BarChart, LineChart, PieChart } from 'echarts/charts';
import {
  DataZoomComponent,
  GridComponent,
  LegendComponent,
  MarkLineComponent,
  TitleComponent,
  TooltipComponent,
} from 'echarts/components';
import * as echartsCore from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';

/**
 * echarts 按需注册基座。全仓只从这里取 echarts——
 * 图表组件用到新图表/组件时在这里追加注册，保证按需打包。
 */
echartsCore.use([
  BarChart,
  LineChart,
  PieChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  TitleComponent,
  DataZoomComponent,
  MarkLineComponent,
  CanvasRenderer,
]);

export const echarts = echartsCore;

export type { ECharts, EChartsCoreOption } from 'echarts/core';
