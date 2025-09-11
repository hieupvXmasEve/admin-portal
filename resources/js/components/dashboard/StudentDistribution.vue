<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { DoughnutChart } from '@/components/ui/chart';
import { useApi } from '@/composables';
import type { ChartData } from 'chart.js';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface ProgramData {
    id: number;
    name: string;
    code: string;
    student_count: number;
    active_count: number;
    graduated_count: number;
    specializations: Array<{
        id: number;
        name: string;
        student_count: number;
        active_count: number;
        graduated_count: number;
    }>;
}

interface ChartDataItem {
    name: string;
    value: number;
    fill: string;
}

interface StudentDistributionData {
    programs: ProgramData[];
    total_students: number;
    chart_data: ChartDataItem[];
}

const api = useApi();
const studentDistribution = ref<StudentDistributionData | null>(null);
const loading = ref<boolean>(true);
const error = ref<string | null>(null);

const totalStudents = computed(() => studentDistribution.value?.total_students ?? 0);
const getColorForProgram = (id: number): string => {
    const colors = ['hsl(var(--chart-1))', 'hsl(var(--chart-2))', 'hsl(var(--chart-3))', 'hsl(var(--chart-4))', 'hsl(var(--chart-5))'];
    return colors[id % colors.length];
};

// Chart colors
const chartColors = [
    'rgba(59, 130, 246, 0.8)', // Blue
    'rgba(16, 185, 129, 0.8)', // Green
    'rgba(245, 158, 11, 0.8)', // Amber
    'rgba(239, 68, 68, 0.8)', // Red
    'rgba(139, 92, 246, 0.8)', // Purple
    'rgba(236, 72, 153, 0.8)', // Pink
];

// Chart.js data configuration
const chartData = computed<ChartData<'doughnut'>>(() => {
    const items = studentDistribution.value?.chart_data ?? [];
    const labels = items.map((item) => item.name);
    const values = items.map((item) => item.value);
    const colors = chartColors.slice(0, items.length);
    const borders = colors.map((color) => color.replace('0.8', '1'));

    return {
        labels,
        datasets: [
            {
                label: 'Students',
                data: values,
                backgroundColor: colors,
                borderColor: borders,
                borderWidth: 2,
                hoverOffset: 4,
            },
        ],
    };
});

// Chart.js options configuration
const chartOptions = computed(() => {
    return {
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                callbacks: {
                    label: function (context: any) {
                        const label = studentDistribution.value?.chart_data[context.dataIndex]?.name || '';
                        const value = context.parsed || 0;
                        const percentage = ((value / totalStudents.value) * 100).toFixed(2);
                        return `${label}: ${value} students (${percentage}%)`;
                    },
                },
            },
        },
        cutout: '60%',
        elements: {
            arc: {
                borderWidth: 2,
            },
        },
    };
});
const loadData = async (): Promise<void> => {
    try {
        const response = await api.get<StudentDistributionData>('/api/dashboard/student-distribution');
        studentDistribution.value = response.data.value?.data ?? null;
    } catch (err) {
        console.error('Failed to load student distribution:', err);
        error.value = 'Failed to load student distribution data';
        toast.error('Failed to load student distribution');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadData();
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-lg">Distribution by Program</CardTitle>
            <div v-if="totalStudents > 0" class="text-muted-foreground text-sm">Total Students: {{ totalStudents.toLocaleString() }}</div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="border-primary h-6 w-6 animate-spin rounded-full border-b-2"></div>
                <span class="text-muted-foreground ml-3">Loading student distribution...</span>
            </div>
            <div v-else-if="error" class="py-8 text-center text-red-600">{{ error }}</div>
            <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <DoughnutChart :data="chartData" :options="chartOptions" height="300px" class="h-[300px]" />
                </div>
                <div>
                    <div class="space-y-3">
                        <div v-for="program in studentDistribution?.programs" :key="program.id" class="bg-muted/50 flex items-center justify-between rounded-lg p-3">
                            <div class="flex items-center gap-3">
                                <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: getColorForProgram(program.id) }"></div>
                                <div>
                                    <div class="text-sm font-medium">{{ program.code }}</div>
                                    <div class="text-muted-foreground text-xs">{{ program.name }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium">{{ program.student_count.toLocaleString() }}</div>
                                <div class="text-muted-foreground text-xs">{{ ((program.student_count / totalStudents) * 100).toFixed(2) }}%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-3">
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div class="text-center">
                            <div class="text-primary text-2xl font-bold">{{ totalStudents.toLocaleString() }}</div>
                            <div class="text-muted-foreground text-sm">Total Students</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">
                                {{ studentDistribution?.programs?.reduce((sum, p) => sum + p.active_count, 0).toLocaleString() ?? '0' }}
                            </div>
                            <div class="text-muted-foreground text-sm">Active Students</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">
                                {{ studentDistribution?.programs?.reduce((sum, p) => sum + p.graduated_count, 0).toLocaleString() ?? '0' }}
                            </div>
                            <div class="text-muted-foreground text-sm">Graduated</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ studentDistribution?.programs?.length ?? 0 }}</div>
                            <div class="text-muted-foreground text-sm">Programs</div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
