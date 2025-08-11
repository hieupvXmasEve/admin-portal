<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed, h } from 'vue';

interface AcademicStanding {
    id: number;
    student: {
        id: number;
        student_id: string;
        full_name: string;
    };
    semester: {
        id: number;
        name: string;
    };
    standing: string;
    standing_label: string;
    gpa: number;
    cumulative_gpa: number;
    effective_date: string;
}

interface Props {
    standings: PaginatedResponse<AcademicStanding>;
    semesters: any[];
    statistics: {
        total: number;
        good: number;
        probation: number;
        suspension: number;
        honors: number;
    };
    filters: any;
}

const props = defineProps<Props>();

const data = computed(() => props.standings.data);

const columns: ColumnDef<AcademicStanding>[] = [
    {
        header: 'Student ID',
        accessorKey: 'student.student_id',
        enableSorting: false,
    },
    {
        header: 'Student Name',
        accessorKey: 'student.full_name',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original.student;
            return h('div', { class: 'font-medium' }, student.full_name);
        },
    },
    {
        header: 'Semester',
        accessorKey: 'semester.name',
        enableSorting: false,
    },
    {
        header: 'Standing',
        accessorKey: 'standing_label',
        enableSorting: false,
        cell: ({ row }) => {
            const standing = row.original.standing;
            const colors = {
                good: 'bg-green-100 text-green-800',
                honors: 'bg-blue-100 text-blue-800',
                probation: 'bg-yellow-100 text-yellow-800',
                suspension: 'bg-red-100 text-red-800',
            };
            const colorClass = colors[standing as keyof typeof colors] || 'bg-gray-100 text-gray-800';
            return h('span', { class: `inline-flex items-center px-2 py-1 rounded-full text-xs ${colorClass}` }, row.original.standing_label);
        },
    },
    {
        header: 'GPA',
        accessorKey: 'gpa',
        enableSorting: false,
        cell: ({ row }) => {
            const gpa = row.original.gpa;
            return h('span', { class: 'font-mono' }, gpa?.toFixed(2) || 'N/A');
        },
    },
    {
        header: 'Cumulative GPA',
        accessorKey: 'cumulative_gpa',
        enableSorting: false,
        cell: ({ row }) => {
            const cgpa = row.original.cumulative_gpa;
            return h('span', { class: 'font-mono' }, cgpa?.toFixed(2) || 'N/A');
        },
    },
    {
        header: 'Effective Date',
        accessorKey: 'effective_date',
        enableSorting: false,
    },
];

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['standings'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize);
};
</script>

<template>
    <Head title="Academic Standings" />

    <div class="space-y-6">
        <div class="bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Academic Standings Management</h2>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold">{{ statistics.total }}</div>
                    <div class="text-sm text-gray-600">Total Records</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold text-green-600">{{ statistics.good }}</div>
                    <div class="text-sm text-gray-600">Good Standing</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold text-blue-600">{{ statistics.honors }}</div>
                    <div class="text-sm text-gray-600">Honors</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold text-yellow-600">{{ statistics.probation }}</div>
                    <div class="text-sm text-gray-600">Probation</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold text-red-600">{{ statistics.suspension }}</div>
                    <div class="text-sm text-gray-600">Suspension</div>
                </CardContent>
            </Card>
        </div>

        <!-- Academic Standings List -->
        <Card>
            <CardHeader>
                <CardTitle>Academic Standings</CardTitle>
                <CardDescription> Current academic standing status for all students </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :data="data" :columns="columns" />

                <div class="mt-4">
                    <DataPagination :pagination-data="standings" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
