<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useGlobalDeleteDialog } from '@/composables';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Campus, Program, Student } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Eye, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

interface Props {
    students: {
        current_page: number;
        data: Student[];
        first_page_url: string;
        from: number | null;
        last_page: number;
        last_page_url: string;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number | null;
        total: number;
    };
    filters: {
        search?: string;
        campus_id?: number;
        program_id?: number;
        enrollment_status?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    campuses: Campus[];
    programs: Program[];
    statistics: {
        total_students: number;
        active_students: number;
        enrolled_students: number;
        graduated_students: number;
        suspended_students: number;
        on_leave_students: number;
    };
}

const props = defineProps<Props>();

// Global delete dialog composable
const deleteDialog = useGlobalDeleteDialog();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Students',
        href: '/students',
    },
];

// Reactive data
const data = computed(() => props.students.data);

const filters = ref({
    search: props.filters.search || '',
    campus_id: props.filters.campus_id ? props.filters.campus_id.toString() : 'all',
    program_id: props.filters.program_id ? props.filters.program_id.toString() : 'all',
    enrollment_status: props.filters.enrollment_status || 'all',
});

// Column definitions with h function
const columns: ColumnDef<Student>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.students.current_page;
            const perPage = props.students.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        accessorKey: 'student_id',
        header: 'Student ID',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'font-medium' }, student.student_id);
        },
    },
    {
        accessorKey: 'full_name',
        header: 'Name',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'font-medium' }, student.full_name);
        },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        enableSorting: false,
        cell: ({ row }) => {
            const email = row.original.email;
            return h('div', { class: 'text-sm text-gray-600' }, email);
        },
    },
    {
        accessorKey: 'campus.name',
        header: 'Campus',
        enableSorting: false,
        cell: ({ row }) => {
            const campus = row.original.campus?.name;
            return campus ? h('div', { class: 'text-sm' }, campus) : h('span', { class: 'text-gray-400' }, 'No campus');
        },
    },
    {
        accessorKey: 'program.name',
        header: 'Program',
        enableSorting: false,
        cell: ({ row }) => {
            const program = row.original.program?.name;
            return program ? h('div', { class: 'text-sm' }, program) : h('span', { class: 'text-gray-400' }, 'No program');
        },
    },
    {
        accessorKey: 'enrollment_status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.enrollment_status;
            const statusColors = {
                admitted: 'bg-gray-100 text-gray-800',
                enrolled: 'bg-blue-100 text-blue-800',
                active: 'bg-green-100 text-green-800',
                on_leave: 'bg-yellow-100 text-yellow-800',
                suspended: 'bg-red-100 text-red-800',
                graduated: 'bg-purple-100 text-purple-800',
                dropped_out: 'bg-gray-100 text-gray-800',
            };
            const colorClass = statusColors[status] || 'bg-gray-100 text-gray-800';
            const displayText = status ? status.replace('_', ' ').toUpperCase() : 'Unknown';

            return h(
                'span',
                {
                    class: `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colorClass}`,
                },
                displayText,
            );
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions',
    },
];

// Search handler for DebouncedInput
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    updateFilters();
};

const handleFilter = () => {
    updateFilters();
};

const clearFilters = () => {
    filters.value = {
        search: '',
        campus_id: 'all',
        program_id: 'all',
        enrollment_status: 'all',
    };
    router.visit('/students', {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return (
        filters.value.search || filters.value.campus_id !== 'all' || filters.value.program_id !== 'all' || filters.value.enrollment_status !== 'all'
    );
});

// View and edit functions
const viewStudent = (student: Student) => {
    router.visit(route('students.show', student.id));
};

const editStudent = (student: Student) => {
    router.visit(route('students.edit', student.id));
};

const deleteStudent = (student: Student) => {
    const deleteRoute = route('students.destroy', student.id);

    deleteDialog.deleteItem(student.full_name, 'student', () => {
        router.delete(deleteRoute, {
            onSuccess: () => {
                console.log('Student deleted successfully');
            },
        });
    });
};

// Pagination handlers
const handlePageChange = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = {
        ...getFilterParams(),
        per_page: pageSize.toString(),
    };

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });

    const url = `/students${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

const getFilterParams = () => {
    return {
        search: filters.value.search,
        campus_id: filters.value.campus_id === 'all' ? '' : filters.value.campus_id,
        program_id: filters.value.program_id === 'all' ? '' : filters.value.program_id,
        enrollment_status: filters.value.enrollment_status === 'all' ? '' : filters.value.enrollment_status,
    };
};

const updateFilters = () => {
    const params = getFilterParams();

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });

    const url = `/students${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};
</script>

<template>
    <Head title="Students" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Header with Add Student Button -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Students</h1>
                <div class="flex items-center gap-2">
                    <Button size="sm" as-child>
                        <Link :href="route('students.create')">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Student
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-6">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex flex-col">
                            <div class="text-2xl font-bold text-blue-600">{{ statistics.total_students }}</div>
                            <div class="text-sm text-gray-600">Total Students</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex flex-col">
                            <div class="text-2xl font-bold text-green-600">{{ statistics.active_students }}</div>
                            <div class="text-sm text-gray-600">Active</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex flex-col">
                            <div class="text-2xl font-bold text-blue-600">{{ statistics.enrolled_students }}</div>
                            <div class="text-sm text-gray-600">Enrolled</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex flex-col">
                            <div class="text-2xl font-bold text-purple-600">{{ statistics.graduated_students }}</div>
                            <div class="text-sm text-gray-600">Graduated</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex flex-col">
                            <div class="text-2xl font-bold text-red-600">{{ statistics.suspended_students }}</div>
                            <div class="text-sm text-gray-600">Suspended</div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex flex-col">
                            <div class="text-2xl font-bold text-yellow-600">{{ statistics.on_leave_students }}</div>
                            <div class="text-sm text-gray-600">On Leave</div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters Section -->
            <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
                <div class="min-w-[200px] flex-1">
                    <DebouncedInput placeholder="Search students..." v-model="filters.search" @debounced="handleSearch" />
                </div>

                <div class="min-w-[150px]">
                    <Select v-model="filters.campus_id" @update:model-value="handleFilter">
                        <SelectTrigger>
                            <SelectValue placeholder="All Campuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Campuses</SelectItem>
                            <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                {{ campus.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[150px]">
                    <Select v-model="filters.program_id" @update:model-value="handleFilter">
                        <SelectTrigger>
                            <SelectValue placeholder="All Programs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Programs</SelectItem>
                            <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                {{ program.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[150px]">
                    <Select v-model="filters.enrollment_status" @update:model-value="handleFilter">
                        <SelectTrigger>
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="admitted">Admitted</SelectItem>
                            <SelectItem value="enrolled">Enrolled</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="on_leave">On Leave</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                            <SelectItem value="graduated">Graduated</SelectItem>
                            <SelectItem value="dropped_out">Dropped Out</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear Filters
                </Button>
            </div>

            <!-- Data Table -->
            <DataTable :data="data" :columns="columns">
                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="viewStudent(row.original)" title="View student">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>View student</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="editStudent(row.original)" title="Edit student">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Edit student</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="deleteStudent(row.original)" title="Delete student">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Delete student</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </template>
            </DataTable>

            <!-- Pagination -->
            <DataPagination :pagination-data="students" @navigate="handlePageChange" @page-size-change="handlePageSizeChange" />
        </div>
    </AppLayout>
</template>
