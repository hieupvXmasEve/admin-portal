<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { nextTick, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

interface TrendData {
    week_start: string;
    week_end: string;
    events_count: number;
    total_registrations: number;
    total_checkins: number;
    total_completions: number;
}

interface Props {
    data: TrendData[];
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

    const labels = props.data.map((item) => {
        const date = new Date(item.week_start);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Registrations',
                    data: props.data.map((item) => item.total_registrations),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Check-ins',
                    data: props.data.map((item) => item.total_checkins),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true,
                },
                {
                    label: 'Completions',
                    data: props.data.map((item) => item.total_completions),
                    borderColor: 'rgb(139, 92, 246)',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
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
                        title: (context) => {
                            const index = context[0].dataIndex;
                            const item = props.data[index];
                            return `Week of ${new Date(item.week_start).toLocaleDateString()}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Week',
                    },
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Participants',
                    },
                    beginAtZero: true,
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
