import type { ChartData, ChartOptions } from 'chart.js';

export interface BaseChartProps {
    data: ChartData<any>;
    options?: ChartOptions<any>;
    height?: string;
    width?: string;
    class?: string;
}

export interface LineChartProps extends BaseChartProps {
    data: ChartData<'line'>;
    options?: ChartOptions<'line'>;
}

export interface BarChartProps extends BaseChartProps {
    data: ChartData<'bar'>;
    options?: ChartOptions<'bar'>;
}

export interface DoughnutChartProps extends BaseChartProps {
    data: ChartData<'doughnut'>;
    options?: ChartOptions<'doughnut'>;
}

// Default options that can be extended
export interface DefaultChartOptions {
    responsive?: boolean;
    maintainAspectRatio?: boolean;
    plugins?: {
        legend?: {
            display?: boolean;
            position?: 'top' | 'bottom' | 'left' | 'right';
        };
        tooltip?: {
            callbacks?: {
                label?: (context: any) => string;
            };
        };
    };
    scales?: {
        y?: {
            beginAtZero?: boolean;
            grid?: {
                color?: string;
            };
            ticks?: {
                callback?: (value: any) => string;
            };
        };
        x?: {
            grid?: {
                display?: boolean;
            };
        };
    };
}
