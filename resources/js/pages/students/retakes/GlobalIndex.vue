<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed, h, reactive, ref } from 'vue';

interface Retake {
    id: number;
    student: {
        id: number;
        student_id: string;
        full_name: string;
    };
    course_offering: {
        unit: {
            unit_code: string;
            unit_name: string;
        };
        semester: {
            name: string;
        };
    };
    attempt_number: number;
    registration_date: string;
    registration_status: string;
}

interface Props {
    retakes: PaginatedResponse<Retake>;
    filters: {
        search?: string;
        unit_id?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const data = computed(() => props.retakes.data);

const loading = ref(false);
const searchForm = reactive({
    search: props.filters.search || '',
    unit_id: props.filters.unit_id || '',
    status: props.filters.status || '',
});

const columns: ColumnDef<Retake>[] = [
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
        header: 'Unit Code',
        accessorKey: 'course_offering.unit.unit_code',
        enableSorting: false,
    },
    {
        header: 'Unit Name',
        accessorKey: 'course_offering.unit.unit_name',
        enableSorting: false,
    },
    {
        header: 'Semester',
        accessorKey: 'course_offering.semester.name',
        enableSorting: false,
    },
    {
        header: 'Attempt #',
        accessorKey: 'attempt_number',
        enableSorting: false,
        cell: ({ row }) => {
            const attempt = row.original.attempt_number;
            return h('span', { class: 'font-mono' }, attempt.toString());
        },
    },
    {
        header: 'Status',
        accessorKey: 'registration_status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.registration_status;
            const colors = {
                registered: 'bg-green-100 text-green-800',
                completed: 'bg-blue-100 text-blue-800',
                dropped: 'bg-red-100 text-red-800',
            };
            const colorClass = colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
            return h('span', { class: `inline-flex items-center px-2 py-1 rounded-full text-xs ${colorClass}` }, status);
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: ({ row }) => {
            const retake = row.original;
            return h(
                'a',
                {
                    href: route('students.retakes.index', retake.student.id),
                    class: 'text-blue-600 hover:text-blue-800 underline',
                },
                'View Student Retakes',
            );
        },
    },
];

const applyFilters = () => {
    loading.value = true;
    router.get(
        route('course-retakes.index'),
        { ...searchForm },
        {
            preserveState: true,
            onFinish: () => (loading.value = false),
        },
    );
};

const clearFilters = () => {
    Object.assign(searchForm, { search: '', unit_id: '', status: '' });
    applyFilters();
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['retakes'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize);
};
</script>

<template>
    <Head title="Course Retakes Management" />

    <div class="space-y-6">
        <div class="bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Course Retakes Management</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage and track student course retakes</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Search and Filters -->
        <Card>
            <CardContent class="p-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <Label for="search">Search Students</Label>
                        <Input id="search" v-model="searchForm.search" placeholder="Search by student name or ID..." @input="applyFilters" />
                    </div>

                    <div>
                        <Label for="status">Status</Label>
                        <Select v-model="searchForm.status" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="registered">Registered</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="dropped">Dropped</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-end">
                        <Button variant="outline" @click="clearFilters" class="w-full"> Clear Filters </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Retakes List -->
        <Card>
            <CardHeader>
                <CardTitle>Course Retake Registrations</CardTitle>
                <CardDescription> Track and manage all course retake registrations </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :data="data" :columns="columns" />

                <div class="mt-4">
                    <DataPagination :pagination-data="retakes" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
