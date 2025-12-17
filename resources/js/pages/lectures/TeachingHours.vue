<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables';
import { PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Clock, X } from 'lucide-vue-next';
import { computed, h } from 'vue';

interface LecturerTeachingHours {
    lecture_id: number;
    lecture_name: string;
    lecture_email: string;
    total_hours: number;
    total_minutes: number;
    session_count: number;
}

interface Semester {
    id: number;
    name: string;
    code: string;
}

interface TeachingHoursFilters {
    semester_id?: string;
    search?: string;
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
    page?: number;
}

const props = defineProps<{
    lecturers: PaginatedResponse<LecturerTeachingHours>;
    filters?: TeachingHoursFilters;
    semesters: Semester[];
}>();

// Reactive data
const data = computed(() => props.lecturers.data);

// Get today's date in YYYY-MM-DD format for max date validation
const today = computed(() => {
    const date = new Date();
    return date.toISOString().split('T')[0];
});

// Initialize filters with useInertiaFilters composable
const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useInertiaFilters<TeachingHoursFilters>({
    baseUrl: '/lectures/teaching-hours',
    initialFilters: {
        semester_id: props.filters?.semester_id || 'all',
        search: props.filters?.search || '',
        date_from: props.filters?.date_from || '2025-01-01',
        date_to: props.filters?.date_to || today.value,
        sort: props.filters?.sort || 'name',
        direction: props.filters?.direction || 'asc',
        per_page: props.filters?.per_page || 15,
    },
    emptyFilters: {
        semester_id: 'all',
        search: '',
        date_from: '2025-01-01',
        date_to: today.value,
        sort: 'name',
        direction: 'asc',
        per_page: 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'asc',
        sort: 'name',
    },
    only: ['lecturers', 'filters'],
    transform: (filters) => ({
        ...filters,
        per_page: Number(filters.per_page),
    }),
});

// Computed sort values for DataTable (unwrap computed refs)
const sortValue = computed(() => {
    const sort = currentSort.value;
    return typeof sort === 'string' ? sort : undefined;
});

const directionValue = computed(() => {
    const dir = currentDirection.value;
    return dir === 'asc' || dir === 'desc' ? dir : undefined;
});

// Semester filter handler
const handleSemesterChange = (value: string | number | bigint | Record<string, any> | null) => {
    handleSelectFilter('semester_id', value, 'all');
};

// Date from handler
const handleDateFromChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    filters.date_from = target.value;
};

// Date to handler
const handleDateToChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    filters.date_to = target.value;
};

// Column definitions
const columns: ColumnDef<LecturerTeachingHours>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.lecturers.current_page;
            const perPage = props.lecturers.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Lecturer Name',
        id: 'name',
        accessorKey: 'lecture_name',
        enableSorting: true,
        cell: ({ row }) => {
            return h(
                Link,
                {
                    href: `/lectures/teaching-hours/${row.original.lecture_id}`,
                    class: 'font-medium hover:underline text-primary',
                },
                () => row.original.lecture_name,
            );
        },
    },
    {
        header: 'Email',
        accessorKey: 'lecture_email',
        enableSorting: false,
        cell: ({ row }) => {
            return h('span', { class: 'text-muted-foreground' }, row.original.lecture_email);
        },
    },
    {
        header: 'Total Hours',
        id: 'hours',
        accessorKey: 'total_hours',
        enableSorting: true,
        cell: ({ row }) => {
            const hours = row.original.total_hours;
            return h('div', { class: 'flex items-center gap-2' }, [h(Clock, { class: 'h-4 w-4 text-muted-foreground' }), h('span', { class: 'font-semibold' }, `${hours.toFixed(2)} hours`)]);
        },
    },
    {
        header: 'Session Count',
        accessorKey: 'session_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.session_count;
            return h('span', { class: 'text-muted-foreground' }, `${count} session${count !== 1 ? 's' : ''}`);
        },
    },
];
</script>

<template>
    <Head title="Lecturer Teaching Hours" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Lecturer Teaching Hours Report</h1>
        <p class="text-muted-foreground mt-1 text-sm">View total teaching hours for lecturers filtered by semester and date range</p>
    </div>

    <!-- Filters Section -->
    <Card class="mb-6">
        <CardContent class="pt-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Semester Filter -->
                <div class="space-y-2">
                    <Label for="semester">Semester</Label>
                    <Select :model-value="filters.semester_id || 'all'" @update:model-value="handleSemesterChange">
                        <SelectTrigger id="semester">
                            <SelectValue placeholder="All Semesters" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()"> {{ semester.name }} ({{ semester.code }}) </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Search Filter -->
                <div class="space-y-2">
                    <Label for="search">Search Lecturer</Label>
                    <DebouncedInput id="search" :model-value="filters.search || ''" placeholder="Search by name or email..." @debounced="handleSearch" />
                </div>

                <!-- Date From Filter -->
                <div class="space-y-2">
                    <Label for="date_from">From Date</Label>
                    <Input id="date_from" type="date" :model-value="filters.date_from" :min="'2025-01-01'" :max="today" @change="handleDateFromChange" />
                </div>

                <!-- Date To Filter -->
                <div class="space-y-2">
                    <Label for="date_to">To Date</Label>
                    <Input id="date_to" type="date" :model-value="filters.date_to" :min="filters.date_from || '2025-01-01'" :max="today" @change="handleDateToChange" />
                </div>
            </div>

            <!-- Clear Filters Button -->
            <div v-if="hasActiveFilters" class="mt-4 flex justify-end">
                <Button variant="ghost" size="sm" @click="clearFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear Filters
                </Button>
            </div>
        </CardContent>
    </Card>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :loading="false" :initial-sort="sortValue" :initial-direction="directionValue" empty-message="No lecturers found with teaching hours in the selected period." @sort-change="handleSortChange" />

    <!-- Pagination -->
    <div v-if="lecturers.last_page > 1" class="mt-4">
        <DataPagination :pagination-data="lecturers" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
