/** 类目轴图表的一条具名序列；data 与 categories 按下标对齐。 */
export interface ChartSeries {
  name: string;
  data: number[];
  /** echarts 可识别的颜色串；缺省走内置调色板。 */
  color?: string;
}

/** 柱图/线图这类「类目轴 + 序列」图表的数据形状。 */
export interface CategoryChart {
  categories: string[];
  series: ChartSeries[];
}

/** 环图/饼图的一个扇区。 */
export interface RingDatum {
  name: string;
  value: number;
  color?: string;
}
