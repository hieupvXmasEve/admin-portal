<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import { PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Clock, Download, X } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { route } from 'ziggy-js';

interface LecturerTeachingHours {
    lecture_id: number;
    lecture_name: string;
    lecture_email: string;
    email_account: string;
    employee_id: string;
    employment_type: string;
    employment_type_label: string;
    course_list: string;
    teaching_subjects: string;
    total_hours: number;
    total_minutes: number;
    session_count: number;
    course_count: number;
}

interface Semester {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
}

interface TeachingHoursFilters {
    semester_id: string;
    search: string;
    date_from: string;
    date_to: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page?: number;
}

const props = defineProps<{
    lecturers: PaginatedResponse<LecturerTeachingHours>;
    filters: TeachingHoursFilters;
    semesters: Semester[];
}>();

const data = computed(() => props.lecturers.data);
const defaultSemesterId = props.filters?.semester_id || 'all';

const { filters, hasActiveFilters, clearAllFilters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, setFilter, currentSort, currentDirection, isLoading } = useDataTable<TeachingHoursFilters>({
    baseUrl: route('lectures.teaching-hours'),
    initialFilters: {
        semester_id: defaultSemesterId,
        search: props.filters?.search ?? '',
        date_from: props.filters?.date_from ?? '',
        date_to: props.filters?.date_to ?? '',
        sort: props.filters?.sort ?? 'name',
        direction: props.filters?.direction ?? 'asc',
        per_page: props.filters?.per_page ?? 15,
        page: props.filters?.page ?? 1,
    },
    defaultValues: {
        semester_id: defaultSemesterId,
        search: '',
        date_from: '',
        date_to: '',
        sort: 'name',
        direction: 'asc',
        per_page: 15,
    },
    only: ['lecturers', 'filters'],
    debounce: 300,
    fieldDebounce: { search: 400 },
    immediateFields: ['semester_id', 'date_from', 'date_to'],
});

const sortValue = computed(() => currentSort.value ?? undefined);
const directionValue = computed(() => currentDirection.value ?? undefined);

const handleSemesterChange = (value: string | number | bigint | Record<string, any> | null) => {
    setFilter('semester_id', String(value ?? defaultSemesterId));
};

const handleDateFromChange = (value: string) => {
    setFilter('date_from', value);
};

const handleDateToChange = (value: string) => {
    setFilter('date_to', value);
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    const exportFilters: Partial<TeachingHoursFilters> = {
        semester_id: filters.semester_id,
        search: filters.search,
        date_from: filters.date_from,
        date_to: filters.date_to,
        sort: filters.sort,
        direction: filters.direction,
    };

    Object.entries(exportFilters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();
    const url = route('lectures.teaching-hours.export');

    return query ? `${url}?${query}` : url;
});

const columns: ColumnDef<LecturerTeachingHours>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.lecturers.current_page;
            const perPage = props.lecturers.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Lecturer name',
        id: 'name',
        accessorKey: 'lecture_name',
        enableSorting: true,
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: route('lectures.teaching-hours.details', row.original.lecture_id),
                    class: 'font-medium text-primary hover:underline',
                },
                () => row.original.lecture_name,
            ),
    },
    {
        header: 'Email account',
        accessorKey: 'email_account',
        enableSorting: false,
        cell: ({ row }) => h('span', { class: 'font-mono text-sm' }, row.original.email_account),
    },
    {
        header: 'Employee ID',
        id: 'employee_id',
        accessorKey: 'employee_id',
        enableSorting: true,
        cell: ({ row }) => h('span', { class: 'font-mono text-sm' }, row.original.employee_id),
    },
    {
        header: 'Type',
        id: 'type',
        accessorKey: 'employment_type_label',
        enableSorting: true,
        cell: ({ row }) => h('span', { class: 'text-sm' }, row.original.employment_type_label),
    },
    {
        header: 'Course dạy',
        accessorKey: 'course_list',
        enableSorting: false,
        cell: ({ row }) => h('div', { class: 'max-w-[280px] whitespace-normal text-sm leading-5' }, row.original.course_list || 'N/A'),
    },
    {
        header: 'Môn giảng dạy của GV',
        accessorKey: 'teaching_subjects',
        enableSorting: false,
        cell: ({ row }) => h('div', { class: 'max-w-[220px] whitespace-normal font-mono text-sm leading-5' }, row.original.teaching_subjects || 'N/A'),
    },
    {
        header: 'Total hours',
        id: 'hours',
        accessorKey: 'total_hours',
        enableSorting: true,
        cell: ({ row }) => {
            const hours = Number(row.original.total_hours);
            return h('div', { class: 'flex items-center gap-2' }, [h(Clock, { class: 'h-4 w-4 text-muted-foreground' }), h('span', { class: 'font-semibold tabular-nums' }, hours.toFixed(2))]);
        },
    },
];
</script>

<template>
    <Head title="Lecturer Teaching Hours" />

    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Lecturer Teaching Hours Report</h1>
            <p class="text-muted-foreground mt-1 text-sm">Summary by lecturer, semester, and optional date range.</p>
        </div>
        <Button as-child variant="outline">
            <a :href="exportUrl">
                <Download class="mr-2 h-4 w-4" />
                Export Excel
            </a>
        </Button>
    </div>

    <Card class="mb-6">
        <CardContent class="pt-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-2">
                    <Label for="semester">Semester</Label>
                    <Select :model-value="filters.semester_id || defaultSemesterId" @update:model-value="handleSemesterChange">
                        <SelectTrigger id="semester">
                            <SelectValue placeholder="Select semester" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()"> {{ semester.name }} ({{ semester.code }})<span v-if="semester.is_active"> - Active</span> </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="search">Search lecturer</Label>
                    <DebouncedInput id="search" :model-value="filters.search || ''" placeholder="Name, email, employee ID, subject..." @debounced="handleSearch" />
                </div>

                <div class="space-y-2">
                    <Label>From date</Label>
                    <DatePicker :model-value="filters.date_from || ''" placeholder="From date" @update:model-value="handleDateFromChange" />
                </div>

                <div class="space-y-2">
                    <Label>To date</Label>
                    <DatePicker :model-value="filters.date_to || ''" placeholder="To date" @update:model-value="handleDateToChange" />
                </div>
            </div>

            <div v-if="hasActiveFilters" class="mt-4 flex justify-end">
                <Button variant="ghost" size="sm" @click="clearAllFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear Filters
                </Button>
            </div>
        </CardContent>
    </Card>

    <DataTable :data="data" :columns="columns" :loading="isLoading" :initial-sort="sortValue" :initial-direction="directionValue" empty-message="No lecturers found with teaching hours in the selected scope." @sort-change="handleSortChange" />

    <div v-if="lecturers.last_page > 1" class="mt-4">
        <DataPagination :pagination-data="lecturers" item-name="lecturers" :page-size-options="[15, 25, 50, 100]" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
