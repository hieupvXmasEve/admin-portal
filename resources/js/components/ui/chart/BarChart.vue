<script setup lang="ts">
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Title, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import type { BarChartProps, DefaultChartOptions } from './types';

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

interface Props extends BarChartProps {
    height?: string;
    width?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    height: '300px',
    width: '100%',
    class: '',
});

const defaultOptions: DefaultChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top',
        },
        tooltip: {
            callbacks: {
                label: function (context: any) {
                    return `${context.dataset.label}: ${context.parsed.y.toLocaleString()}`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(0, 0, 0, 0.1)',
            },
            ticks: {
                callback: function (value: any) {
                    return value.toLocaleString();
                },
            },
        },
        x: {
            grid: {
                display: false,
            },
        },
    },
};

const mergedOptions = computed(() => {
    if (!props.options) {
        return defaultOptions;
    }

    // Deep merge options with defaults
    return {
        ...defaultOptions,
        ...props.options,
        plugins: {
            ...defaultOptions.plugins,
            ...props.options.plugins,
            legend: {
                ...defaultOptions.plugins?.legend,
                ...props.options.plugins?.legend,
            },
            tooltip: {
                ...defaultOptions.plugins?.tooltip,
                ...props.options.plugins?.tooltip,
                callbacks: {
                    ...defaultOptions.plugins?.tooltip?.callbacks,
                    ...props.options.plugins?.tooltip?.callbacks,
                },
            },
        },
        scales: {
            ...defaultOptions.scales,
            ...props.options.scales,
            y: {
                ...defaultOptions.scales?.y,
                ...props.options.scales?.y,
                grid: {
                    ...defaultOptions.scales?.y?.grid,
                    ...props.options.scales?.y?.grid,
                },
                ticks: {
                    ...defaultOptions.scales?.y?.ticks,
                    ...props.options.scales?.y?.ticks,
                },
            },
            x: {
                ...defaultOptions.scales?.x,
                ...props.options.scales?.x,
                grid: {
                    ...defaultOptions.scales?.x?.grid,
                    ...props.options.scales?.x?.grid,
                },
            },
        },
    };
});
</script>

<template>
    <div
        :class="props.class"
        :style="{
            height: height,
            width: width,
        }"
    >
        <Bar :data="data" :options="mergedOptions" :plugins="plugins" class="h-full w-full" />
    </div>
</template>
