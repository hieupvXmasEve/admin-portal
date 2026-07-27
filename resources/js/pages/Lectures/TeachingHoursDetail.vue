<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, BookOpen, Calendar, Clock, X } from 'lucide-vue-next';
import { computed, h } from 'vue';

interface ClassSession {
    id: number;
    session_date: string;
    start_time: string;
    end_time: string;
    calculated_duration: number; // in hours
    session_type: string;
    status: string;
    // Joins
    course_offering_id: number;
    unit_code: string;
    unit_name: string;
    unit_type: string;
    formatted_time: string;
}

interface UnitTypeOption {
    value: string;
    label: string;
}

interface TeachingHoursDetailFilters {
    unit_type: string;
    date_from: string;
    date_to: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page?: number;
}

const props = defineProps<{
    lecture: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        employee_id: string;
    };
    sessions: PaginatedResponse<ClassSession>;
    stats: {
        total_hours: number;
        total_minutes: number;
        total_sessions: number;
        unique_courses: number;
    };
    filters?: TeachingHoursDetailFilters;
    unitTypes: UnitTypeOption[];
}>();

// Reactive data
const data = computed(() => props.sessions.data);

// Date helpers
const formatDate = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const todayDate = new Date();
const defaultDateTo = formatDate(new Date(todayDate.getFullYear(), todayDate.getMonth(), 15)); // 15th of current month
const defaultDateFrom = formatDate(new Date(todayDate.getFullYear(), todayDate.getMonth() - 1, 16)); // 16th of previous month
const today = formatDate(todayDate);

const { filters, hasActiveFilters, clearAllFilters, handleSortChange, handlePaginationNavigate, handlePageSizeChange, setFilter, currentSort, currentDirection, isLoading } = useDataTable<TeachingHoursDetailFilters>({
    baseUrl: route('lectures.teaching-hours.details', props.lecture.id),
    initialFilters: {
        unit_type: props.filters?.unit_type ?? 'all',
        date_from: props.filters?.date_from ?? defaultDateFrom,
        date_to: props.filters?.date_to ?? defaultDateTo,
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : 'date',
        direction: props.filters?.direction === 'asc' || props.filters?.direction === 'desc' ? props.filters.direction : 'desc',
        per_page: props.filters?.per_page ?? 15,
        page: props.filters?.page ?? 1,
    },
    defaultValues: {
        unit_type: 'all',
        date_from: defaultDateFrom,
        date_to: defaultDateTo,
        sort: 'date',
        direction: 'desc',
        per_page: 15,
    },
    only: ['sessions', 'stats', 'filters'],
    immediateFields: ['unit_type', 'date_from', 'date_to'],
});

// Computed sort values for DataTable
const sortValue = computed(() => {
    const sort = currentSort.value;
    return typeof sort === 'string' ? sort : undefined;
});

const directionValue = computed(() => {
    const dir = currentDirection.value;
    return dir === 'asc' || dir === 'desc' ? dir : undefined;
});

// Date handlers
const handleDateFromChange = (value: string) => {
    if (!value || value <= today) {
        setFilter('date_from', value);
    }
};

const handleDateToChange = (value: string) => {
    if (!value || (value >= filters.date_from && value <= today)) {
        setFilter('date_to', value);
    }
};

const handleUnitTypeChange = (value: string | number | bigint | Record<string, any> | null) => {
    setFilter('unit_type', String(value ?? 'all'));
};

// Column definitions
const columns: ColumnDef<ClassSession>[] = [
    {
        header: 'Date',
        id: 'date',
        accessorKey: 'session_date', // Sorting key
        enableSorting: true,
        cell: ({ row }) => {
            const date = new Date(row.original.session_date);
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-medium' }, date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })),
                h('span', { class: 'text-xs text-muted-foreground' }, date.toLocaleDateString('en-US', { weekday: 'long' })),
            ]);
        },
    },
    {
        header: 'Time',
        id: 'time',
        accessorKey: 'start_time',
        enableSorting: true,
        cell: ({ row }) => {
            // Since we don't have formatted_time directly in the raw query result easily unless appends loaded,
            // let's rely on standard formatting or if request passes it.
            // But ClassSession model has getFormattedTimeAttribute.
            // The raw query in Action might not load appends automatically if we select specific columns.
            // Let's format manually from start_time and end_time strings for safety.
            const start = row.original.start_time.substring(0, 5);
            const end = row.original.end_time.substring(0, 5);
            return h('span', { class: 'text-sm' }, `${start} - ${end}`);
        },
    },
    {
        header: 'Course',
        id: 'course',
        enableSorting: false,
        cell: ({ row }) => {
            return h('div', { class: 'flex flex-col' }, [
                h('span', { class: 'font-medium' }, row.original.unit_code),
                h('span', { class: 'text-xs text-muted-foreground truncated max-w-[200px]' }, row.original.unit_name),
                h(Badge, { variant: 'outline', class: 'mt-1 w-fit text-[10px]' }, () => (row.original.unit_type ? row.original.unit_type.toUpperCase() : 'N/A')),
            ]);
        },
    },
    {
        header: 'Duration (minutes)',
        id: 'duration',
        accessorKey: 'calculated_duration',
        enableSorting: true,
        cell: ({ row }) => {
            const hours = Number(row.original.calculated_duration);
            return h('div', { class: 'flex items-center gap-1' }, [h(Clock, { class: 'h-3 w-3 text-muted-foreground' }), h('span', { class: 'font-semibold' }, `${hours.toFixed(2)}`)]);
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.status;
            let className = '';
            if (status === 'completed') className = 'bg-green-100 text-green-800 hover:bg-green-100 border-green-200';
            if (status === 'cancelled') className = 'bg-red-100 text-red-800 hover:bg-red-100 border-red-200';
            if (status === 'scheduled') className = 'bg-blue-100 text-blue-800 hover:bg-blue-100 border-blue-200';

            return h(Badge, { variant: 'outline', class: className }, () => status.charAt(0).toUpperCase() + status.slice(1));
        },
    },
];
</script>

<template>
    <Head :title="`Teaching Hours - ${lecture.first_name} ${lecture.last_name}`" />

    <!-- Breadcrumb / Back Navigation -->
    <div class="mb-6 flex items-center gap-4">
        <Button variant="outline" size="icon" as-child>
            <Link :href="route('lectures.teaching-hours')">
                <ArrowLeft class="h-4 w-4" />
            </Link>
        </Button>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ lecture.first_name }} {{ lecture.last_name }}</h1>
            <p class="text-muted-foreground flex items-center gap-2 text-sm">
                <span class="bg-muted rounded px-1 font-mono">{{ lecture.employee_id }}</span>
                <span>&bull;</span>
                <span>{{ lecture.email }}</span>
            </p>
        </div>
    </div>

    <div class="mb-6 grid gap-6 md:grid-cols-4">
        <!-- Stat Cards -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium"> Total Hours </CardTitle>
                <Clock class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stats.total_hours.toFixed(2) }}</div>
                <p class="text-muted-foreground text-xs">{{ stats.total_minutes }} minutes</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium"> Total Sessions </CardTitle>
                <Calendar class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stats.total_sessions }}</div>
                <p class="text-muted-foreground text-xs">Recorded sessions</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium"> Courses </CardTitle>
                <BookOpen class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ stats.unique_courses }}</div>
                <p class="text-muted-foreground text-xs">Unique courses taught</p>
            </CardContent>
        </Card>

        <!-- Quick Actions / Export could go here -->
    </div>

    <!-- Filters -->
    <Card class="mb-6">
        <CardContent class="pt-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <!-- Date Range -->
                <div class="space-y-2">
                    <Label>Date Range</Label>
                    <div class="flex items-center gap-2">
                        <DatePicker :model-value="filters.date_from" placeholder="From date" @update:model-value="handleDateFromChange" class="flex-1" />
                        <span class="text-muted-foreground">-</span>
                        <DatePicker :model-value="filters.date_to" placeholder="To date" @update:model-value="handleDateToChange" class="flex-1" />
                    </div>
                </div>

                <!-- Unit Type Filter (Single Select) -->
                <div class="space-y-2">
                    <Label>Filter Unit Type</Label>
                    <Select :model-value="filters.unit_type" @update:model-value="handleUnitTypeChange">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select Unit Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Unit Types</SelectLabel>
                                <SelectItem value="all">All Unit Types</SelectItem>
                                <SelectItem v-for="type in unitTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Clear Filters -->
                <div class="flex items-end justify-end">
                    <Button v-if="hasActiveFilters" variant="ghost" @click="clearAllFilters"> <X class="mr-2 h-4 w-4" /> Clear Filters </Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :loading="isLoading" :initial-sort="sortValue" :initial-direction="directionValue" empty-message="No teaching sessions found for the selected criteria." @sort-change="handleSortChange" />

    <!-- Pagination -->
    <div v-if="sessions.last_page > 1" class="mt-4">
        <DataPagination :pagination-data="sessions" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
