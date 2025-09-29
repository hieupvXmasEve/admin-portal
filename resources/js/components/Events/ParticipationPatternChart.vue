<script setup lang="ts">
import { ref, onMounted, watch, nextTick } from 'vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

interface PatternData {
    day?: string
    time_slot?: string
    duration_range?: string
    events_count: number
    avg_participation: number
    avg_success_rate: number
}

interface Props {
    data: PatternData[]
    type: 'day' | 'time' | 'duration'
}

const props = defineProps<Props>()
const chartCanvas = ref<HTMLCanvasElement>()
let chartInstance: Chart | null = null

const createChart = async () => {
    await nextTick()

    if (!chartCanvas.value || !props.data?.length) return

    // Destroy existing chart
    if (chartInstance) {
        chartInstance.destroy()
    }

    const ctx = chartCanvas.value.getContext('2d')
    if (!ctx) return

    const labels = props.data.map(item => {
        if (props.type === 'day') return item.day
        if (props.type === 'time') return item.time_slot
        return item.duration_range
    })

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Events Count',
                    data: props.data.map(item => item.events_count),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                    yAxisID: 'y',
                },
                {
                    label: 'Avg Participation',
                    data: props.data.map(item => item.avg_participation || 0),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1,
                    yAxisID: 'y',
                },
                {
                    label: 'Success Rate (%)',
                    data: props.data.map(item => item.avg_success_rate || 0),
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
                            if (context.dataset.label === 'Success Rate (%)') {
                                return `${context.dataset.label}: ${context.parsed.y.toFixed(1)}%`
                            }
                            if (context.dataset.label === 'Avg Participation') {
                                return `${context.dataset.label}: ${context.parsed.y.toFixed(1)}`
                            }
                            return `${context.dataset.label}: ${context.parsed.y}`
                        },
                    },
                },
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: getXAxisTitle(),
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
                        text: 'Success Rate (%)',
                    },
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            },
        },
    })
}

const getXAxisTitle = (): string => {
    switch (props.type) {
        case 'day':
            return 'Day of Week'
        case 'time':
            return 'Time of Day'
        case 'duration':
            return 'Event Duration'
        default:
            return 'Category'
    }
}

onMounted(() => {
    createChart()
})

watch(() => [props.data, props.type], () => {
    createChart()
}, { deep: true })
</script>

<template>
    <div class="w-full h-64">
        <canvas ref="chartCanvas"></canvas>
    </div>
</template>
