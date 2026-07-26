<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed, h } from 'vue';

interface Enrollment {
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
    enrollment_date: string;
    status: string;
}

interface Props {
    enrollments: PaginatedResponse<Enrollment>;
    filters: any;
}

const props = defineProps<Props>();

const data = computed(() => props.enrollments.data);

const columns: ColumnDef<Enrollment>[] = [
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
        header: 'Enrollment Date',
        accessorKey: 'enrollment_date',
        enableSorting: false,
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.status;
            const colors = {
                enrolled: 'bg-green-100 text-green-800',
                dropped: 'bg-red-100 text-red-800',
                completed: 'bg-blue-100 text-blue-800',
            };
            const colorClass = colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
            return h('span', { class: `inline-flex items-center px-2 py-1 rounded-full text-xs ${colorClass}` }, status);
        },
    },
];

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['enrollments'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize);
};
</script>

<template>
    <Head title="Student Enrollments & Holds" />

    <div class="space-y-6">
        <div class="bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Student Enrollments & Holds Management</h2>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Student Enrollments</CardTitle>
                <CardDescription> Track and manage student semester enrollments </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :data="data" :columns="columns" />

                <div class="mt-4">
                    <DataPagination :pagination-data="enrollments" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
