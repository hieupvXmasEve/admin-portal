<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { nextTick, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

interface MonthlyData {
    month: string;
    month_name: string;
    events_count: number;
    total_participants: number;
    total_gold_awarded: number;
    completion_rate: number;
}

interface Props {
    data: MonthlyData[];
}

const props = defineProps<Props>();
const chartCanvas = ref<HTMLCanvasElement>();
let chartInstance: Chart | null = null;

const createChart = async () => {
    await nextTick();

    if (!chartCanvas.value || !props.data?.length) return;

    // Destroy existing chart
    if (chartInstance) {
        chartInstance.destroy();
    }

    const ctx = chartCanvas.value.getContext('2d');
    if (!ctx) return;

    const labels = props.data.map((item) => item.month_name);

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Events Count',
                    data: props.data.map((item) => item.events_count),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                    yAxisID: 'y',
                },
                {
                    label: 'Total Participants',
                    data: props.data.map((item) => item.total_participants),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1,
                    yAxisID: 'y',
                },
                {
                    label: 'Completion Rate (%)',
                    data: props.data.map((item) => item.completion_rate),
                    type: 'line',
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index',
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            if (context.dataset.label === 'Completion Rate (%)') {
                                return `${context.dataset.label}: ${context.parsed.y.toFixed(1)}%`;
                            }
                            return `${context.dataset.label}: ${context.parsed.y}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Month',
                    },
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Count',
                    },
                    beginAtZero: true,
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Completion Rate (%)',
                    },
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        drawOnChartArea: false,
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
