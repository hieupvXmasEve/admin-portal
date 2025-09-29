<script setup lang="ts">
import { Chart, registerables } from 'chart.js';
import { nextTick, onMounted, ref, watch } from 'vue';

Chart.register(...registerables);

interface GoldData {
    event_id: number;
    event_title: string;
    gold_per_participant: number;
    total_awarded: number;
    participants_awarded: number;
}

interface Props {
    data: GoldData[];
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

    // Take top 10 events by gold awarded
    const topEvents = props.data.slice(0, 10);

    const labels = topEvents.map((item) => {
        // Truncate long titles
        return item.event_title.length > 20 ? item.event_title.substring(0, 20) + '...' : item.event_title;
    });

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Total Gold Awarded',
                    data: topEvents.map((item) => item.total_awarded),
                    backgroundColor: 'rgba(251, 191, 36, 0.8)',
                    borderColor: 'rgb(251, 191, 36)',
                    borderWidth: 1,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        title: (context) => {
                            const index = context[0].dataIndex;
                            return topEvents[index].event_title;
                        },
                        label: (context) => {
                            const index = context.dataIndex;
                            const item = topEvents[index];
                            return [`Total Gold: ${item.total_awarded}`, `Per Participant: ${item.gold_per_participant}`, `Participants Awarded: ${item.participants_awarded}`];
                        },
                    },
                },
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Gold Awarded',
                    },
                    beginAtZero: true,
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Events',
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
