<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { BarChart, LineChart } from '@/components/ui/chart';
import { useApi } from '@/composables/useApiRequest';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
interface EnrollmentTrendItem {
    semester: string;
    date: string;
    enrollments: number;
}

interface AdmissionTrendItem {
    date: string;
    new_students: number;
}

interface EnrollmentGrowthData {
    admission_trend: AdmissionTrendItem[];
    enrollment_trend: EnrollmentTrendItem[];
}

const api = useApi();
const dataRef = ref<EnrollmentGrowthData | null>(null);
const loading = ref<boolean>(true);
const error = ref<string | null>(null);

const admissionChartData = computed(() => {
    return {
        labels: dataRef.value
            ? dataRef.value?.admission_trend.map((item) => {
                  const date = new Date(item.date + '-01');
                  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
              })
            : [],
        datasets: [
            {
                label: 'New Students',
                data: dataRef.value ? dataRef.value?.admission_trend.map((item) => item.new_students) : [],
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                fill: true,
                tension: 0.4,
            },
        ],
    };
});

const loadData = async (): Promise<void> => {
    try {
        const response = await api.get<EnrollmentGrowthData>('/api/dashboard/enrollment-growth');
        dataRef.value = response.data.value?.data ?? null;
    } catch (err) {
        console.error('Failed to load enrollment growth:', err);
        error.value = 'Failed to load enrollment growth data';
        toast.error('Failed to load enrollment growth');
    } finally {
        loading.value = false;
    }
};

// Bar chart data for enrollment trend
const enrollmentChartData = computed(() => {
    return {
        labels: dataRef.value ? dataRef.value?.enrollment_trend.map((item) => item.semester) : [],
        datasets: [
            {
                label: 'Enrollments',
                data: dataRef.value ? dataRef.value?.enrollment_trend.map((item) => item.enrollments) : [],
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false,
            },
        ],
    };
});

// Chart options for line chart
const lineChartOptions = computed(() => {
    return {
        plugins: {
            tooltip: {
                callbacks: {
                    label: function (context: any) {
                        return `${context.dataset.label}: ${context.parsed.y} students`;
                    },
                },
            },
        },
    };
});

// Chart options for bar chart
const barChartOptions = computed(() => {
    return {
        plugins: {
            tooltip: {
                callbacks: {
                    label: function (context: any) {
                        return `${context.dataset.label}: ${context.parsed.y} enrollments`;
                    },
                },
            },
        },
    };
});

onMounted(() => {
    loadData();
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-lg">Enrollment Growth</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Monthly Admission Trend</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <LineChart :data="admissionChartData" :options="lineChartOptions" height="300px" class="h-[300px]" />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-lg">Semester Enrollment Trend</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BarChart :data="enrollmentChartData" :options="barChartOptions" height="300px" class="h-[300px]" />
                    </CardContent>
                </Card>
            </div>
        </CardContent>
    </Card>
</template>
