<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import BarChart from '@/components/ui/chart/BarChart.vue';
import DoughnutChart from '@/components/ui/chart/DoughnutChart.vue';
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
        pass_fail: Array<{ label: string; total: number }>;
        attendance: Array<{ bucket: string; total: number }>;
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
    'A': { color: '#22c55e', range: '85-89' },
    'A-': { color: '#4ade80', range: '80-84' },
    'B+': { color: '#84cc16', range: '75-79' },
    'B': { color: '#eab308', range: '70-74' },
    'B-': { color: '#facc15', range: '65-69' },
    'C+': { color: '#f97316', range: '60-64' },
    'C': { color: '#fb923c', range: '55-59' },
    'C-': { color: '#fdba74', range: '50-54' },
    'D+': { color: '#ef4444', range: '45-49' },
    'D': { color: '#f87171', range: '40-44' },
    'F': { color: '#b91c1c', range: '0-39' },
    'N/A': { color: '#94a3b8', range: '' },
};

const PASS_FAIL_COLORS: Record<string, string> = {
    'Pass': '#10b981', // Green
    'Fail': '#ef4444', // Red
};

const ATTENDANCE_COLORS: Record<string, string> = {
    '>=80%': '#8b5cf6', // Purple
    '<80%': '#f59e0b',  // Amber
};

const gradeChartData = computed(() => {
    const grades = props.data.grade_distribution;
    const labels = grades.map(item => item.final_letter_grade);

    // Create one dataset per grade to show in legend with count
    const datasets = grades.map((item, index) => {
        const grade = item.final_letter_grade || 'N/A';
        const meta = GRADE_METADATA[grade] || { color: '#3b82f6', range: '' };

        // Data array with 0s except at the correct index for this grade
        const dataArr = new Array(grades.length).fill(0);
        dataArr[index] = item.total;

        return {
            label: `${grade}${meta.range ? ' (' + meta.range + ')' : ''}: ${item.total} pts`,
            backgroundColor: meta.color,
            data: dataArr,
            stack: 'stack1',
        };
    });

    return {
        labels,
        datasets,
    };
});

const gradeChartOptions = {
    scales: {
        x: { stacked: true },
        y: { stacked: true }
    }
};

const passFailChartData = computed(() => {
    const labels = props.data.pass_fail.map(item => item.label);
    const data = props.data.pass_fail.map(item => item.total);
    const backgroundColors = labels.map(label => PASS_FAIL_COLORS[label] || '#3b82f6');

    return {
        labels,
        datasets: [
            {
                backgroundColor: backgroundColors,
                data,
            },
        ],
    };
});

const attendanceChartData = computed(() => {
    const labels = props.data.attendance.map(item => item.bucket);
    const data = props.data.attendance.map(item => item.total);
    const backgroundColors = labels.map(label => ATTENDANCE_COLORS[label] || '#3b82f6');

    return {
        labels,
        datasets: [
            {
                backgroundColor: backgroundColors,
                data,
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
        enableSorting: false
    },
    {
        header: 'Enrollment',
        accessorKey: 'enrollment',
        enableSorting: false
    },
    {
        header: 'Pass Rate',
        id: 'pass_rate',
        enableSorting: false
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
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                        {{ data.unit.code }}: {{ data.unit.name }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Unit Academic Statistics Analysis</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-medium flex items-center gap-2">
                    <Calendar class="h-4 w-4" />
                    Semester Filter
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-end gap-4">
                    <div class="w-full max-w-xs space-y-2">
                        <Label>Semester</Label>
                        <Select :model-value="String(filters.semester_id)" @update:model-value="(val) => {
                            filters.semester_id = val === 'all' ? 'all' : Number(val);
                            applyFilters();
                        }">
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
                <CardContent>
                    <BarChart :data="gradeChartData" :options="gradeChartOptions" height="350px" />
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
                    <DoughnutChart :data="passFailChartData" height="300px" />
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
                    <DoughnutChart :data="attendanceChartData" height="300px" />
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
                            <span
                                :class="['font-mono font-semibold', row.original.pass_rate >= 80 ? 'text-green-600' : 'text-yellow-600']">
                                {{ row.original.pass_rate }}%
                            </span>
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
