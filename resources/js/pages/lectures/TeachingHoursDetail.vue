<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
// import { MultiSelect } from '@/components/ui/multi-select'; // Assuming this component exists or we use something similar
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, BookOpen, Calendar, Clock, Filter, X } from 'lucide-vue-next';
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
    formatted_time: string;
}

interface CourseOption {
    id: number;
    name: string;
}

interface TeachingHoursDetailFilters {
    course_offering_ids?: number[];
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: string;
    per_page?: number;
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
    courses: CourseOption[];
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
const defaultDateTo = formatDate(new Date(todayDate.getFullYear(), todayDate.getMonth(), 16)); // 16th of current month
const defaultDateFrom = formatDate(new Date(todayDate.getFullYear(), todayDate.getMonth() - 1, 16)); // 16th of previous month
const currentMonthEnd = formatDate(new Date(todayDate.getFullYear(), todayDate.getMonth() + 1, 0)); // last day of current month

// Initialize filters
const {
    filters,
    hasActiveFilters,
    clearFilters,
    handleSortChange,
    handlePaginationNavigate,
    handlePageSizeChange,
    currentSort,
    currentDirection,
    handleSelectFilter, // Added missing destructure
} = useInertiaFilters<TeachingHoursDetailFilters>({
    baseUrl: `/lectures/teaching-hours/${props.lecture.id}`,
    initialFilters: {
        course_offering_ids: Array.isArray(props.filters?.course_offering_ids) ? props.filters.course_offering_ids.map((id) => Number(id)) : props.filters?.course_offering_ids ? [Number(props.filters.course_offering_ids)] : [],
        date_from: props.filters?.date_from || defaultDateFrom,
        date_to: props.filters?.date_to || defaultDateTo,
        sort: props.filters?.sort || 'date',
        direction: props.filters?.direction || 'desc',
        per_page: props.filters?.per_page || 15,
    },
    emptyFilters: {
        course_offering_ids: [],
        date_from: defaultDateFrom,
        date_to: defaultDateTo,
        sort: 'date',
        direction: 'desc',
        per_page: 15,
    },
    defaultValues: {
        per_page: 15,
        sort: 'date',
        direction: 'desc',
    },
    only: ['sessions', 'stats', 'filters'],
    transform: (filters) => ({
        ...filters,
        per_page: Number(filters.per_page),
        // Ensure array is passed correctly
        course_offering_ids: filters.course_offering_ids?.length ? filters.course_offering_ids : undefined,
    }),
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
const handleDateFromChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    filters.date_from = target.value;
};

const handleDateToChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    filters.date_to = target.value;
};

const handleCourseToggle = (courseId: number) => {
    const current = filters.course_offering_ids || [];
    const index = current.indexOf(courseId);
    let newIds: number[];

    if (index === -1) {
        newIds = [...current, courseId];
    } else {
        newIds = current.filter((id) => id !== courseId);
    }

    // Use handleSelectFilter to trigger the update
    // Passing undefined as default value to avoid checking against string 'all' for array
    handleSelectFilter('course_offering_ids', newIds, undefined);
};

const isCourseSelected = (courseId: number) => {
    return (filters.course_offering_ids || []).includes(courseId);
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
            return h('div', { class: 'flex flex-col' }, [h('span', { class: 'font-medium' }, row.original.unit_code), h('span', { class: 'text-xs text-muted-foreground truncated max-w-[200px]' }, row.original.unit_name)]);
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
            let variant: 'default' | 'secondary' | 'destructive' | 'outline' = 'outline';
            if (status === 'completed') variant = 'default'; // In Shadcn badge, default is usually primary color. Wait, user might want green.
            // Adjusting based on common patterns.
            // Success is not a standard variant in Shadcn Badge unless customized.
            // Let's use outline with conditional classes if needed, or stick to simple variants.
            // Actually we can add class: 'bg-green-100 text-green-800' etc.

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
                        <Input type="date" :model-value="filters.date_from" :max="currentMonthEnd" @change="handleDateFromChange" class="flex-1" />
                        <span class="text-muted-foreground">-</span>
                        <Input type="date" :model-value="filters.date_to" :min="filters.date_from" :max="currentMonthEnd" @change="handleDateToChange" class="flex-1" />
                    </div>
                </div>

                <!-- Course Filter (Checkbox Dropdown) -->
                <div class="space-y-2">
                    <Label>Filter Courses</Label>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="w-full justify-between">
                                <span class="truncate">
                                    {{ filters.course_offering_ids?.length ? `${filters.course_offering_ids.length} selected` : 'All Courses' }}
                                </span>
                                <Filter class="ml-2 h-4 w-4 opacity-50" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="max-h-80 w-72 overflow-y-auto">
                            <DropdownMenuLabel>Select Courses</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem v-for="course in courses" :key="course.id" :checked="isCourseSelected(course.id)" :model-value="isCourseSelected(course.id)" @update:model-value="handleCourseToggle(course.id)" @select.prevent>
                                <span class="truncate">{{ course.name }}</span>
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <!-- Clear Filters -->
                <div class="flex items-end justify-end">
                    <Button v-if="hasActiveFilters" variant="ghost" @click="clearFilters"> <X class="mr-2 h-4 w-4" /> Clear Filters </Button>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :loading="false" :initial-sort="sortValue" :initial-direction="directionValue" empty-message="No teaching sessions found for the selected criteria." @sort-change="handleSortChange" />

    <!-- Pagination -->
    <div v-if="sessions.last_page > 1" class="mt-4">
        <DataPagination :pagination-data="sessions" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
