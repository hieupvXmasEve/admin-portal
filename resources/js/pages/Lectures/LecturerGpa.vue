<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { lecturerRoutes } from '@/utils/routes';
import { Head } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Download, Search, X } from 'lucide-vue-next';
import { computed, h } from 'vue';

interface LecturerGpaRow {
    lecturer_id: number;
    lecturer_name: string;
    email_account: string;
    employee_id: string;
    type: string;
    type_label: string;
    courses: string[];
    courses_display: string;
    gpa: number | null;
    evaluated_classes_count: number;
    classes_count: number;
    responses_count: number;
}

interface LecturerGpaFilters {
    search: string;
    semester_id: string | null;
    page: number;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface SemesterOption {
    id: number;
    name: string;
    code: string | null;
}

const props = defineProps<{
    rows: PaginatedResponse<LecturerGpaRow>;
    filters: Partial<LecturerGpaFilters>;
    semesters: SemesterOption[];
    active_semester: SemesterOption | null;
}>();

const data = computed(() => props.rows.data);

const { filters, setFilter, clearAllFilters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, hasActiveFilters, isLoading, currentSort, currentDirection } = useDataTable<LecturerGpaFilters>({
    baseUrl: lecturerRoutes.gpa(),
    initialFilters: {
        search: props.filters.search ?? '',
        semester_id: props.filters.semester_id ?? null,
        page: props.filters.page ?? 1,
        per_page: props.filters.per_page ?? 15,
        sort: typeof props.filters.sort === 'string' ? props.filters.sort : 'lecturer_name',
        direction: props.filters.direction ?? 'asc',
    },
    defaultValues: {
        search: '',
        semester_id: props.filters.semester_id ?? null,
        page: 1,
        per_page: 15,
        sort: 'lecturer_name',
        direction: 'asc',
    },
    only: ['rows', 'filters', 'active_semester'],
    debounce: 300,
    fieldDebounce: { search: 300 },
    immediateFields: ['semester_id'],
});

const semesterSelectValue = computed(() => filters.semester_id ?? 'none');

const exportToExcel = () => {
    const params = new URLSearchParams();

    if (filters.search) params.append('search', filters.search);
    if (filters.semester_id) params.append('semester_id', filters.semester_id);
    if (filters.sort) params.append('sort', filters.sort);
    if (filters.direction) params.append('direction', filters.direction);

    const queryString = params.toString();
    window.open(`${lecturerRoutes.gpaExport()}${queryString ? `?${queryString}` : ''}`, '_blank');
};

const formatGpa = (gpa: number | null) => (gpa === null ? 'N/A' : gpa.toFixed(2));

const columns: ColumnDef<LecturerGpaRow>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.rows.current_page;
            const perPage = props.rows.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Lecturer name',
        accessorKey: 'lecturer_name',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-medium text-sm' }, row.original.lecturer_name),
    },
    {
        header: 'Email account',
        accessorKey: 'email_account',
        enableSorting: false,
        cell: ({ row }) => h('code', { class: 'rounded bg-muted px-1.5 py-0.5 text-xs' }, row.original.email_account || 'N/A'),
    },
    {
        header: 'Employee ID',
        accessorKey: 'employee_id',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-mono text-sm' }, row.original.employee_id),
    },
    {
        header: 'Type',
        accessorKey: 'type',
        enableSorting: true,
        cell: ({ row }) => h(Badge, { variant: 'outline', class: 'text-xs' }, () => row.original.type_label),
    },
    {
        header: 'Course dạy',
        accessorKey: 'courses_display',
        enableSorting: false,
        cell: ({ row }) => h('div', { class: 'max-w-[360px] whitespace-normal text-sm leading-6' }, row.original.courses_display || 'N/A'),
    },
    {
        header: 'GPA',
        accessorKey: 'gpa',
        enableSorting: true,
        cell: ({ row }) =>
            h('div', { class: 'flex flex-col gap-1' }, [
                h('span', { class: 'text-base font-semibold tabular-nums' }, formatGpa(row.original.gpa)),
                h('span', { class: 'text-muted-foreground text-xs' }, `${row.original.evaluated_classes_count}/${row.original.classes_count} classes`),
            ]),
    },
    {
        header: 'Responses',
        accessorKey: 'responses_count',
        enableSorting: true,
        cell: ({ row }) => h('span', { class: 'text-sm tabular-nums' }, row.original.responses_count),
    },
];
</script>

<template>
    <Head title="Lecturer GPA" />

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Lecturer GPA</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ active_semester ? `${active_semester.name} summary` : 'No semester selected' }}
            </p>
        </div>

        <Button variant="outline" size="sm" @click="exportToExcel">
            <Download class="mr-2 h-4 w-4" />
            Export Excel
        </Button>
    </div>

    <div class="bg-card mt-6 space-y-4 rounded-lg border p-4">
        <div class="grid gap-4 md:grid-cols-[minmax(240px,1fr)_240px_auto] md:items-end">
            <div class="space-y-2">
                <Label for="lecturer-gpa-search">Search</Label>
                <div class="relative">
                    <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                    <Input id="lecturer-gpa-search" :model-value="filters.search" placeholder="Search lecturer, employee ID, email or course..." class="pl-8" :disabled="isLoading" @update:model-value="(value) => handleSearch(value)" />
                </div>
            </div>

            <div class="space-y-2">
                <Label for="lecturer-gpa-semester">Semester</Label>
                <Select :model-value="semesterSelectValue" :disabled="isLoading || semesters.length === 0" @update:model-value="(value) => value !== 'none' && setFilter('semester_id', String(value))">
                    <SelectTrigger id="lecturer-gpa-semester">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="semesters.length === 0" value="none">No semesters</SelectItem>
                        <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()"> {{ semester.name }}{{ semester.code ? ` (${semester.code})` : '' }} </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Button v-if="hasActiveFilters" variant="ghost" size="sm" :disabled="isLoading" @click="clearAllFilters">
                <X class="mr-2 h-4 w-4" />
                Clear filters
            </Button>
        </div>
    </div>

    <div class="mt-6">
        <DataTable :data="data" :columns="columns" :loading="isLoading" :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange" />
    </div>

    <DataPagination :pagination-data="rows" item-name="lecturer GPA rows" :page-size-options="[15, 25, 50, 100]" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
