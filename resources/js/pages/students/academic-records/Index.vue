<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Award, BookOpen, CheckCircle, FileText, TrendingUp } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

interface AcademicRecord {
    id: number;
    semester: {
        id: number;
        name: string;
    };
    unit: {
        unit_code: string;
        unit_name: string;
        credit_points: number;
    };
    final_grade: string;
    status: string;
}

interface Props {
    student: {
        id: number;
        student_id: string;
        full_name: string;
        program?: { name: string };
        specialization?: { name: string };
    };
    records: PaginatedResponse<AcademicRecord>;
    semesters: Array<{ id: number; name: string }>;
    analytics: {
        current_gpa: number;
        average_gpa: number;
        total_credits_completed: number;
        academic_standing: string;
        gpa_trends: any[];
    };
    filters: {
        semester_id?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const data = computed(() => props.records.data);

const loading = ref(false);
const filters = ref({
    semester_id: props.filters.semester_id || '',
    status: props.filters.status || '',
});

const columns: ColumnDef<AcademicRecord>[] = [
    {
        header: 'Semester',
        accessorKey: 'semester.name',
        enableSorting: false,
    },
    {
        header: 'Unit Code',
        accessorKey: 'unit.unit_code',
        enableSorting: false,
        cell: ({ row }) => {
            const unitCode = row.original.unit.unit_code;
            return h('span', { class: 'font-mono' }, unitCode);
        },
    },
    {
        header: 'Unit Name',
        accessorKey: 'unit.unit_name',
        enableSorting: false,
        cell: ({ row }) => {
            const unitName = row.original.unit.unit_name;
            return h('div', { class: 'font-medium' }, unitName);
        },
    },
    {
        header: 'Credits',
        accessorKey: 'unit.credit_points',
        enableSorting: false,
        cell: ({ row }) => {
            const credits = row.original.unit.credit_points;
            return h('span', { class: 'font-mono' }, credits.toString());
        },
    },
    {
        header: 'Grade',
        accessorKey: 'final_grade',
        enableSorting: false,
        cell: ({ row }) => {
            const grade = row.original.final_grade;
            return h('span', { class: 'font-mono font-bold' }, grade || 'N/A');
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.status;
            const colors = {
                completed: 'bg-green-100 text-green-800',
                in_progress: 'bg-blue-100 text-blue-800',
                withdrawn: 'bg-red-100 text-red-800',
            };
            const colorClass = colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
            return h('span', { class: `inline-flex items-center px-2 py-1 rounded-full text-xs ${colorClass}` }, status);
        },
    },
];

const getStandingColor = (standing: string) => {
    const colors = {
        honors: 'text-blue-600',
        good: 'text-green-600',
        probation: 'text-yellow-600',
        suspension: 'text-red-600',
    };
    return colors[standing as keyof typeof colors] || 'text-gray-600';
};

const applyFilters = () => {
    loading.value = true;
    router.get(
        route('students.academic-records.index', props.student.id),
        { ...filters.value },
        {
            preserveState: true,
            onFinish: () => (loading.value = false),
        },
    );
};

const clearFilters = () => {
    filters.value = { semester_id: '', status: '' };
    applyFilters();
};

const navigateToTranscript = () => {
    router.visit(route('students.academic-records.transcript', props.student.id));
};

const navigateToGpaHistory = () => {
    router.visit(route('students.academic-records.gpa-history', props.student.id));
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['records'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize);
};
</script>

<template>
    <Head title="Academic Records" />

    <div class="space-y-6">
        <div class="bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Academic Records - {{ student.full_name }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Student ID: {{ student.student_id }} |
                            {{ student.program?.name }}
                            <span v-if="student.specialization">- {{ student.specialization.name }}</span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="navigateToTranscript" class="inline-flex items-center">
                            <FileText class="mr-2 h-4 w-4" />
                            View Transcript
                        </Button>
                        <Button variant="outline" @click="navigateToGpaHistory" class="inline-flex items-center">
                            <TrendingUp class="mr-2 h-4 w-4" />
                            GPA History
                        </Button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Performance Summary Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center">
                        <Award class="h-8 w-8 text-blue-600" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Current GPA</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ analytics.current_gpa?.toFixed(2) || 'N/A' }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center">
                        <BookOpen class="h-8 w-8 text-green-600" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Credits Completed</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ analytics.total_credits_completed }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center">
                        <TrendingUp class="h-8 w-8 text-purple-600" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Average GPA</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ analytics.average_gpa?.toFixed(2) || 'N/A' }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center">
                        <CheckCircle :class="['h-8 w-8', getStandingColor(analytics.academic_standing)]" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Standing</p>
                            <p class="text-sm font-bold text-gray-900 capitalize">
                                {{ analytics.academic_standing }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="p-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <Label for="semester">Semester</Label>
                        <Select v-model="filters.semester_id" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All Semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Semesters</SelectItem>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label for="status">Status</Label>
                        <Select v-model="filters.status" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
                                <SelectItem value="withdrawn">Withdrawn</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-end">
                        <Button variant="outline" @click="clearFilters" class="w-full"> Clear Filters </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Academic Records Table -->
        <Card>
            <CardHeader>
                <CardTitle>Academic Records</CardTitle>
            </CardHeader>
            <CardContent>
                <DataTable :data="data" :columns="columns" />

                <div class="mt-4">
                    <DataPagination :pagination-data="records" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
