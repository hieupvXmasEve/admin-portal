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

interface Student {
    id: number;
    student_code: string;
    full_name: string;
    email: string;
    program?: { name: string };
    specialization?: { name: string };
}

interface Props {
    students: PaginatedResponse<Student>;
    programs: Array<{ id: number; name: string }>;
    semesters: Array<{ id: number; name: string }>;
    filters: {
        search?: string;
        program_id?: string;
        semester_id?: string;
    };
}

const props = defineProps<Props>();

const loading = ref(false);
const searchForm = reactive({
    search: props.filters.search || '',
    program_id: props.filters.program_id || '',
    semester_id: props.filters.semester_id || '',
});

const data = computed(() => props.students.data);

const tableColumns: ColumnDef<Student>[] = [
    {
        header: 'Student ID',
        accessorKey: 'student_code',
        enableSorting: false,
    },
    {
        header: 'Full Name',
        accessorKey: 'full_name',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'font-medium' }, student.full_name);
        },
    },
    {
        header: 'Email',
        accessorKey: 'email',
        enableSorting: false,
    },
    {
        header: 'Program',
        accessorKey: 'program.name',
        enableSorting: false,
        cell: ({ row }) => {
            const program = row.original.program;
            return program ? h('span', program.name) : h('span', { class: 'text-gray-400' }, 'No program');
        },
    },
    {
        header: 'Specialization',
        accessorKey: 'specialization.name',
        enableSorting: false,
        cell: ({ row }) => {
            const specialization = row.original.specialization;
            return specialization ? h('span', specialization.name) : h('span', { class: 'text-gray-400' }, 'No specialization');
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original;
            return h(
                'a',
                {
                    href: route('students.academic-records.index', student.id),
                    class: 'text-blue-600 hover:text-blue-800 underline',
                },
                'View Records',
            );
        },
    },
];

const applyFilters = () => {
    loading.value = true;
    router.get(
        route('academic-records.index'),
        { ...searchForm },
        {
            preserveState: true,
            onFinish: () => (loading.value = false),
        },
    );
};

const clearFilters = () => {
    Object.assign(searchForm, { search: '', program_id: '', semester_id: '' });
    applyFilters();
};

// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    // Add page size handling logic here
    console.log('Page size changed to:', pageSize);
};
</script>

<template>
    <Head title="Academic Records Management" />

    <div class="space-y-6">
        <!-- Search and Filters -->
        <Card>
            <CardContent class="p-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <Label for="search">Search Students</Label>
                        <Input id="search" v-model="searchForm.search" placeholder="Search by name, ID, or email..." @input="applyFilters" />
                    </div>

                    <div>
                        <Label for="program">Program</Label>
                        <Select v-model="searchForm.program_id" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All Programs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">All Programs</SelectItem>
                                <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                    {{ program.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-end">
                        <Button variant="outline" @click="clearFilters" class="w-full"> Clear Filters </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Students List -->
        <Card>
            <CardHeader>
                <CardTitle>Students with Academic Records</CardTitle>
                <CardDescription> Click on a student to view their detailed academic records </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :data="data" :columns="tableColumns" :loading="loading" />

                <div class="mt-4">
                    <DataPagination :pagination-data="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
