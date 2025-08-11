<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed, h } from 'vue';

interface ProgramChange {
    id: number;
    student: {
        id: number;
        student_id: string;
        full_name: string;
    };
    current_program: {
        name: string;
    };
    requested_program: {
        name: string;
    };
    status: string;
    requested_at: string;
}

interface Props {
    programChanges: PaginatedResponse<ProgramChange>;
    filters: any;
    statistics: {
        total: number;
        pending: number;
        approved: number;
        rejected: number;
    };
}

const props = defineProps<Props>();

const data = computed(() => props.programChanges.data);

const columns: ColumnDef<ProgramChange>[] = [
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
        header: 'Current Program',
        accessorKey: 'current_program.name',
        enableSorting: false,
    },
    {
        header: 'Requested Program',
        accessorKey: 'requested_program.name',
        enableSorting: false,
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.status;
            const colors = {
                pending: 'bg-yellow-100 text-yellow-800',
                approved: 'bg-green-100 text-green-800',
                rejected: 'bg-red-100 text-red-800',
            };
            const colorClass = colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
            return h('span', { class: `inline-flex items-center px-2 py-1 rounded-full text-xs ${colorClass}` }, status);
        },
    },
    {
        header: 'Requested At',
        accessorKey: 'requested_at',
        enableSorting: false,
    },
];

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['programChanges'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize);
};
</script>

<template>
    <Head title="Program Change Requests" />

    <div class="space-y-6">
        <div class="bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Program Change Requests</h2>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold">{{ statistics.total }}</div>
                    <div class="text-sm text-gray-600">Total Requests</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold text-yellow-600">{{ statistics.pending }}</div>
                    <div class="text-sm text-gray-600">Pending</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold text-green-600">{{ statistics.approved }}</div>
                    <div class="text-sm text-gray-600">Approved</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div class="text-2xl font-bold text-red-600">{{ statistics.rejected }}</div>
                    <div class="text-sm text-gray-600">Rejected</div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Program Change Requests</CardTitle>
                <CardDescription> Manage student program and specialization change requests </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :data="data" :columns="columns" />

                <div class="mt-4">
                    <DataPagination :pagination-data="programChanges" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
