<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { nextTick, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

interface StatusData {
    draft: number;
    published: number;
    completed: number;
    cancelled: number;
}

interface Props {
    data: StatusData;
}

const props = defineProps<Props>();
const chartCanvas = ref<HTMLCanvasElement>();
let chartInstance: Chart | null = null;

const createChart = async () => {
    await nextTick();

    if (!chartCanvas.value || !props.data) return;

    // Destroy existing chart
    if (chartInstance) {
        chartInstance.destroy();
    }

    const ctx = chartCanvas.value.getContext('2d');
    if (!ctx) return;

    const data = [props.data.draft, props.data.published, props.data.completed, props.data.cancelled];

    const labels = ['Draft', 'Published', 'Completed', 'Cancelled'];
    const colors = [
        'rgba(156, 163, 175, 0.8)', // Gray for draft
        'rgba(59, 130, 246, 0.8)', // Blue for published
        'rgba(16, 185, 129, 0.8)', // Green for completed
        'rgba(239, 68, 68, 0.8)', // Red for cancelled
    ];

    chartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [
                {
                    data,
                    backgroundColor: colors,
                    borderColor: colors.map((color) => color.replace('0.8', '1')),
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const total = data.reduce((sum, value) => sum + value, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : '0';
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
};

onMounted(() => {
    createChart();
});

watch(
    () => props.data,
    () => {
        createChart();
    },
    { deep: true },
);
</script>

<template>
    <div class="h-64 w-full">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>
