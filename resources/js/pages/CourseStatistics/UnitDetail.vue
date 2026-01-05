<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DoughnutChart from '@/components/ui/chart/DoughnutChart.vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, BarChart3, BookOpen, Calendar, ClipboardList, Download, Eye, PieChart, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    data: {
        unit: {
            id: number;
            code: string;
            name: string;
        };
        grade_distribution: Array<{ final_letter_grade: string; total: number }>;
        pass_fail: Array<{ label: string; total: number; percentage: number }>;
        attendance: Array<{ bucket: string; total: number; percentage: number }>;
        offerings: Array<{
            id: number;
            section: string;
            semester: string;
            lecturer: string;
            enrollment: number;
            pass_rate: number;
        }>;
    };
    semesters: Array<{ id: number; name: string; code: string }>;
    filters: {
        semester_id?: number;
    };
}

const props = defineProps<Props>();

const filters = ref({
    semester_id: props.filters.semester_id || 'all',
});

const applyFilters = () => {
    const queryParams: Record<string, any> = {};
    if (filters.value.semester_id && filters.value.semester_id !== 'all') {
        queryParams.semester_id = filters.value.semester_id;
    }

    router.get(route('course-statistics.units.show', { unitId: props.data.unit.id }), queryParams, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Chart Data Preparations
// Chart Metadata for Grades
const GRADE_METADATA: Record<string, { color: string; range: string }> = {
    'A+': { color: '#10b981', range: '90-100' },
    A: { color: '#22c55e', range: '85-89' },
    'A-': { color: '#4ade80', range: '80-84' },
    'B+': { color: '#84cc16', range: '75-79' },
    B: { color: '#eab308', range: '70-74' },
    'B-': { color: '#facc15', range: '65-69' },
    'C+': { color: '#f97316', range: '60-64' },
    C: { color: '#fb923c', range: '55-59' },
    'C-': { color: '#fdba74', range: '50-54' },
    'D+': { color: '#ef4444', range: '45-49' },
    D: { color: '#f87171', range: '40-44' },
    F: { color: '#b91c1c', range: '0-39' },
    'N/A': { color: '#94a3b8', range: '' },
};

const PASS_FAIL_COLORS: Record<string, string> = {
    Pass: '#10b981', // Green
    Fail: '#ef4444', // Red
};

const ATTENDANCE_COLORS: Record<string, string> = {
    '>=80%': '#8b5cf6', // Purple
    '<80%': '#f59e0b', // Amber
};

const gradeChartData = computed(() => {
    const grades = props.data.grade_distribution;
    const labels = grades.map((item) => item.final_letter_grade);
    const data = grades.map((item) => item.total);

    // Calculate percentages for the plugin
    const total = data.reduce((acc, curr) => acc + curr, 0);
    const percentages = data.map((val) => (total > 0 ? ((val / total) * 100).toFixed(1) : '0'));

    const backgroundColors = grades.map((item) => {
        const grade = item.final_letter_grade || 'N/A';
        const meta = GRADE_METADATA[grade] || { color: '#3b82f6', range: '' };
        return meta.color;
    });

    return {
        labels: grades.map((item) => {
            const grade = item.final_letter_grade || 'N/A';
            const meta = GRADE_METADATA[grade] || { range: '' };
            return `${grade}${meta.range ? ' (' + meta.range + ')' : ''}`;
        }),
        datasets: [
            {
                data,
                backgroundColor: backgroundColors,
                percentages,
                showCount: true,
            },
        ],
    };
});

const gradeChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'right' as const,
        },
    },
};

const passFailChartData = computed(() => {
    const labels = props.data.pass_fail.map((item) => item.label);
    const data = props.data.pass_fail.map((item) => item.total);
    const percentages = props.data.pass_fail.map((item) => item.percentage);
    const backgroundColors = labels.map((label) => PASS_FAIL_COLORS[label] || '#3b82f6');

    return {
        labels,
        datasets: [
            {
                backgroundColor: backgroundColors,
                data,
                percentages,
            },
        ],
    };
});

const attendanceChartData = computed(() => {
    const labels = props.data.attendance.map((item) => item.bucket);
    const data = props.data.attendance.map((item) => item.total);
    const percentages = props.data.attendance.map((item) => item.percentage);

    // Dynamic color assignment based on bucket string
    const backgroundColors = labels.map((label) => {
        if (label.startsWith('>=')) return '#8b5cf6'; // Purple
        if (label.startsWith('<')) return '#f59e0b'; // Amber
        return ATTENDANCE_COLORS[label] || '#3b82f6';
    });

    return {
        labels,
        datasets: [
            {
                backgroundColor: backgroundColors,
                data,
                percentages,
            },
        ],
    };
});

const columns: ColumnDef<any>[] = [
    {
        header: 'Section',
        accessorKey: 'section',
        enableSorting: false,
    },
    {
        header: 'Semester',
        accessorKey: 'semester',
        enableSorting: false,
    },
    {
        header: 'Lecturer',
        accessorKey: 'lecturer',
        enableSorting: false,
    },
    {
        header: 'Enrollment',
        accessorKey: 'enrollment',
        enableSorting: false,
    },
    {
        header: 'Pass Rate',
        id: 'pass_rate',
        enableSorting: false,
    },
    {
        header: 'Actions',
        id: 'actions',
    },
];

const goToOfferingDetail = (id: number) => {
    router.visit(`/course-statistics/${id}/students`);
};

const goToAssessmentScoresPage = (courseOfferingId: number) => {
    router.visit(`/course-statistics/${courseOfferingId}/assessment-scores`);
};

const exportStatistics = (courseOfferingId: number) => {
    window.location.href = `/course-statistics/${courseOfferingId}/export-combined`;
};

const doughnutLabelPlugin = {
    id: 'doughnutLabel',
    afterDatasetsDraw(chart: any) {
        const { ctx } = chart;

        chart.data.datasets.forEach((dataset: any, i: number) => {
            const meta = chart.getDatasetMeta(i);
            if (!meta.hidden) {
                meta.data.forEach((element: any, index: number) => {
                    const value = dataset.data[index];
                    // Use server-provided percentage if available, otherwise fallback to calculation
                    let percentage;

                    if (dataset.percentages && dataset.percentages[index] !== undefined) {
                        percentage = dataset.percentages[index] + '%';
                    } else {
                        const total = dataset.data.reduce((acc: number, curr: number) => acc + curr, 0);
                        percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                    }

                    if (value > 0) {
                        const { x, y } = element.tooltipPosition();

                        ctx.save();
                        ctx.fillStyle = '#ffffff';
                        ctx.textAlign = 'center';

                        if (dataset.showCount) {
                            // Display Value and Percentage on two lines
                            ctx.font = 'bold 14px sans-serif';
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(value.toString(), x, y - 2);

                            ctx.font = 'bold 11px sans-serif';
                            ctx.textBaseline = 'top';
                            ctx.fillText(percentage, x, y + 2);
                        } else {
                            // Display only Percentage
                            ctx.font = 'bold 12px sans-serif';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(percentage, x, y);
                        }

                        ctx.restore();
                    }
                });
            }
        });
    },
};

const doughnutPlugins = [doughnutLabelPlugin];
</script>

<template>
    <Head :title="`${data.unit.code} Statistics`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" as-child>
                    <Link :href="route('course-statistics.index', { semester_id: filters.semester_id })">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to List
                    </Link>
                </Button>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">{{ data.unit.code }}: {{ data.unit.name }}</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Unit Academic Statistics Analysis</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-medium">
                    <Calendar class="h-4 w-4" />
                    Semester Filter
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-end gap-4">
                    <div class="w-full max-w-xs space-y-2">
                        <Label>Semester</Label>
                        <Select
                            :model-value="String(filters.semester_id)"
                            @update:model-value="
                                (val) => {
                                    filters.semester_id = val === 'all' ? 'all' : Number(val);
                                    applyFilters();
                                }
                            "
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All semesters</SelectItem>
                                <SelectItem v-for="s in semesters" :key="s.id" :value="String(s.id)">
                                    {{ s.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Grade Distribution -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BarChart3 class="h-5 w-5" />
                        Grade Distribution
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex items-center justify-center">
                    <DoughnutChart :data="gradeChartData" :options="gradeChartOptions" :plugins="doughnutPlugins" height="350px" />
                </CardContent>
            </Card>

            <!-- Pass/Fail -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <PieChart class="h-5 w-5" />
                        Pass / Fail Rate
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex items-center justify-center">
                    <DoughnutChart :data="passFailChartData" :plugins="doughnutPlugins" height="300px" />
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Attendance -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Attendance Overview
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex items-center justify-center">
                    <DoughnutChart :data="attendanceChartData" :plugins="doughnutPlugins" height="300px" />
                </CardContent>
            </Card>

            <!-- Offerings List -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BookOpen class="h-5 w-5" />
                        Course Offerings
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <DataTable :columns="columns" :data="data.offerings">
                        <template #cell-pass_rate="{ row }">
                            <span :class="['font-mono font-semibold', row.original.pass_rate >= 80 ? 'text-green-600' : 'text-yellow-600']"> {{ row.original.pass_rate }}% </span>
                        </template>
                        <template #cell-actions="{ row }">
                            <Button variant="ghost" size="sm" @click="goToOfferingDetail(row.original.id)">
                                <Eye class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" @click="goToAssessmentScoresPage(row.original.id)">
                                <ClipboardList class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" @click="exportStatistics(row.original.id)">
                                <Download class="h-4 w-4" />
                            </Button>
                        </template>
                    </DataTable>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
