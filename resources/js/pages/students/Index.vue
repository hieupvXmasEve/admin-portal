<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useGlobalDeleteDialog } from '@/composables';
import type { Campus, Program, Student } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
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
        status?: string;
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

const studentsRef = ref(props.students.data);

// Reactive data
const data = computed(() => studentsRef.value);

const filters = ref({
    search: props.filters.search || '',
    campus_id: props.filters.campus_id ? props.filters.campus_id.toString() : 'all',
    program_id: props.filters.program_id ? props.filters.program_id.toString() : 'all',
    status: props.filters.status || 'all',
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
        accessorKey: 'status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.status;
            const statusColors: Record<string, string> = {
                active: 'bg-green-100 text-green-800',
                admitted: 'bg-yellow-100 text-yellow-800',
                inactive: 'bg-gray-100 text-gray-800',
                suspended: 'bg-red-100 text-red-800',
                graduated: 'bg-blue-100 text-blue-800',
                dropped_out: 'bg-red-100 text-red-800',
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
        status: 'all',
    };
    router.visit(studentRoutes.list(), {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || filters.value.campus_id !== 'all' || filters.value.program_id !== 'all';
});

// View and edit functions
const viewStudent = (student: Student) => {
    router.visit(studentRoutes.show(student.id));
};

const editStudent = (student: Student) => {
    router.visit(studentRoutes.edit(student.id));
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

    const url = `${studentRoutes.list()}${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

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
        status: filters.value.status === 'all' ? '' : filters.value.status,
    };
};

const updateFilters = () => {
    const params = getFilterParams();

    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value) searchParams.set(key, value);
    });

    const url = `${studentRoutes.list()}${searchParams.toString() ? '?' + searchParams.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};
</script>

<template>
    <Head title="Students Management" />

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold">Students Management</h1>
                <p class="text-sm text-gray-500">Manage all students in the system</p>
            </div>
            <Link :href="studentRoutes.create()">
                <Button> <Plus class="mr-2 h-4 w-4" /> Add Student </Button>
            </Link>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardContent class="p-4">
                    <h3 class="text-sm font-medium text-gray-500">Total Students</h3>
                    <p class="text-2xl font-semibold">{{ statistics.total_students }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <h3 class="text-sm font-medium text-gray-500">Active Students</h3>
                    <p class="text-2xl font-semibold">{{ statistics.active_students }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <h3 class="text-sm font-medium text-gray-500">Graduated Students</h3>
                    <p class="text-2xl font-semibold">{{ statistics.graduated_students }}</p>
                </CardContent>
            </Card>
        </div>

        <Card class="mt-4">
            <CardContent class="p-4">
                <div class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex w-full items-center gap-2 md:w-auto">
                        <DebouncedInput
                            v-model="filters.search"
                            placeholder="Search by name, email or student ID..."
                            class="w-full md:w-64"
                            @update:model-value="handleSearch"
                        />
                        <Select v-model="filters.campus_id" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by campus..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Campuses</SelectItem>
                                <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="filters.program_id" @update:model-value="handleFilter">
                            <SelectTrigger class="w-full md:w-48">
                                <SelectValue placeholder="Filter by program..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Programs</SelectItem>
                                <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                    {{ program.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button v-if="hasActiveFilters" variant="ghost" @click="clearFilters">
                            <X class="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>
                </div>
                <div class="mt-4">
                    <DataTable :columns="columns" :data="data" :total="students.total" @page-changed="handlePageChange">
                        <template #cell-actions="{ row }">
                            <div class="flex items-center space-x-2">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="viewStudent(row.original)">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> View </TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="editStudent(row.original)">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> Edit </TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button variant="ghost" size="icon" @click="deleteStudent(row.original)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent> Delete </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </template>
                    </DataTable>
                </div>
            </CardContent>
        </Card>

        <DataPagination :pagination-data="students" @page-changed="handlePageChange" @page-size-changed="handlePageSizeChange" />
    </div>
</template>
