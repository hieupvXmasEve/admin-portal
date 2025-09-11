<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { useApi } from '@/composables/useApiRequest';
import { VisLine, VisXYContainer } from '@unovis/vue';
import { format } from 'date-fns';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface AdmissionTrendData {
    date: string;
    new_students: number;
}

interface EnrollmentTrendData {
    semester: string;
    date: string;
    enrollments: number;
}

interface EnrollmentGrowthData {
    admission_trend: AdmissionTrendData[];
    enrollment_trend: EnrollmentTrendData[];
}

const data = ref<EnrollmentGrowthData | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const api = useApi();

const admissionChartData = computed(() => {
    if (!data.value?.admission_trend) return [];
    return data.value.admission_trend.map((item) => ({
        ...item,
        formattedDate: format(new Date(item.date + '-01'), 'MMM yyyy'),
    }));
});

const enrollmentChartData = computed(() => {
    if (!data.value?.enrollment_trend) return [];
    return data.value.enrollment_trend.map((item) => ({
        ...item,
        formattedDate: format(new Date(item.date), 'MMM yyyy'),
    }));
});

const totalNewStudents = computed(() => {
    if (!data.value?.admission_trend) return 0;
    return data.value.admission_trend.reduce((sum, item) => sum + item.new_students, 0);
});

const currentEnrollments = computed(() => {
    if (!data.value?.enrollment_trend) return 0;
    return data.value.enrollment_trend.reduce((sum, item) => sum + item.enrollments, 0);
});

onMounted(async () => {
    try {
        const response = await api.get('/api/dashboard/enrollment-growth');
        data.value = response.data.value?.data ?? null;
    } catch (err) {
        console.error('Failed to load enrollment growth data:', err);
        error.value = 'Failed to load enrollment growth data';
        toast.error('Failed to load chart data');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2"> 📈Enrollment Growth </CardTitle>
            <div v-if="data" class="text-muted-foreground text-sm">New Students: {{ totalNewStudents }} | Current Enrollments: {{ currentEnrollments }}</div>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="border-primary h-8 w-8 animate-spin rounded-full border-b-2"></div>
                <span class="text-muted-foreground ml-3">Loading chart data...</span>
            </div>

            <div v-else-if="error" class="py-8 text-center text-red-600">
                <div class="mb-2 text-xl">❌</div>
                <div>{{ error }}</div>
            </div>

            <div v-else-if="data" class="space-y-6">
                <!-- New Student Admissions -->
                <div>
                    <h4 class="text-muted-foreground mb-3 text-sm font-medium">New Student Admissions (Last 3 Years)</h4>
                    <div class="h-[200px]">
                        <VisXYContainer v-if="admissionChartData.length > 0" :data="admissionChartData" class="h-full w-full">
                            <VisLine :x="(d: any) => d.formattedDate" :y="(d: any) => d.new_students" :curve-type="'curveMonotoneX'" :stroke-width="2" color="hsl(var(--chart-1))" />
                        </VisXYContainer>
                        <div v-else class="text-muted-foreground flex h-full items-center justify-center">No admission data available</div>
                    </div>
                </div>

                <!-- Current Enrollments by Semester -->
                <div>
                    <h4 class="text-muted-foreground mb-3 text-sm font-medium">Current Enrollments by Semester</h4>
                    <div class="h-[200px]">
                        <VisXYContainer v-if="enrollmentChartData.length > 0" :data="enrollmentChartData" class="h-full w-full">
                            <VisLine :x="(d: any) => d.formattedDate" :y="(d: any) => d.enrollments" :curve-type="'curveMonotoneX'" :stroke-width="2" color="hsl(var(--chart-2))" />
                        </VisXYContainer>
                        <div v-else class="text-muted-foreground flex h-full items-center justify-center">No enrollment data available</div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="grid grid-cols-2 gap-4 border-t pt-4 md:grid-cols-4">
                    <div class="text-center">
                        <div class="text-chart-1 text-2xl font-bold">{{ totalNewStudents }}</div>
                        <div class="text-muted-foreground text-sm">Total New Students</div>
                    </div>
                    <div class="text-center">
                        <div class="text-chart-2 text-2xl font-bold">{{ currentEnrollments }}</div>
                        <div class="text-muted-foreground text-sm">Current Enrollments</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold">{{ data.admission_trend.length }}</div>
                        <div class="text-muted-foreground text-sm">Months Tracked</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold">{{ data.enrollment_trend.length }}</div>
                        <div class="text-muted-foreground text-sm">Semesters</div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
