export { default as ChartTooltip } from './ChartTooltip.vue'
export { default as LineChart } from './LineChart.vue'
export { default as BarChart } from './BarChart.vue'
export { default as DoughnutChart } from './DoughnutChart.vue'

export function defaultColors(count: number = 3) {
  const quotient = Math.floor(count / 2)
  const remainder = count % 2

  const primaryCount = quotient + remainder
  const secondaryCount = quotient
  return [
    ...Array.from(new Array(primaryCount).keys()).map(i => `hsl(var(--vis-primary-color) / ${1 - (1 / primaryCount) * i})`),
    ...Array.from(new Array(secondaryCount).keys()).map(i => `hsl(var(--vis-secondary-color) / ${1 - (1 / secondaryCount) * i})`),
  ]
}

export type {
    BaseChartProps,
    LineChartProps,
    BarChartProps,
    DoughnutChartProps,
    DefaultChartOptions,
} from './types'
