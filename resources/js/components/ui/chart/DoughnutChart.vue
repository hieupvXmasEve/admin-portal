<script setup lang="ts">
import { ArcElement, Chart as ChartJS, DoughnutController, Legend, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import type { DoughnutChartProps, DefaultChartOptions } from './types';

// Register Chart.js components
ChartJS.register(ArcElement, Tooltip, Legend, DoughnutController);

interface Props extends DoughnutChartProps {
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
                    const label = context.label || '';
                    const value = context.parsed || 0;
                    return `${label}: ${value.toLocaleString()}`;
                },
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
        <Doughnut :data="data" :options="mergedOptions" class="h-full w-full" />
    </div>
</template>
