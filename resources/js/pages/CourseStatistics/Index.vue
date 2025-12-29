<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { BarChart3, BookOpen, Eye, TrendingDown, TrendingUp, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface UnitStatistic {
    id: number;
    unit_code: string;
    unit_name: string;
    credit_hours: number;
    offerings_count: number;
    total_students: number;
    average_attendance: number;
    average_grade: number;
    pass_rate: number;
}

interface UnitFilters {
    semester_id: string;
    search: string;
    per_page: number;
    sort: string;
    direction: 'asc' | 'desc';
}

const props = defineProps<{
    statistics: PaginatedResponse<UnitStatistic>;
    semesters: Array<{ id: number; name: string; code: string }>;
    filters: Partial<UnitFilters>;
}>();

const {
    filters,
    hasActiveFilters,
    clearFilters,
    handleSearch,
    handleSelectFilter,
    handleSortChange,
    handlePaginationNavigate,
    handlePageSizeChange,
    currentSort,
    currentDirection,
} = useInertiaFilters<UnitFilters>({
    baseUrl: route('course-statistics.index'),
    initialFilters: {
        semester_id: String(props.filters?.semester_id || ''),
        search: props.filters?.search || '',
        sort: props.filters?.sort || 'unit_code',
        direction: (props.filters?.direction as 'asc' | 'desc') || 'asc',
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        sort: 'unit_code',
        direction: 'asc',
        per_page: 15,
    },
    only: ['statistics', 'filters'],
});

const columns: ColumnDef<UnitStatistic>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
    },
    {
        header: 'Unit',
        accessorKey: 'unit_code',
        id: 'unit',
    },
    {
        header: 'Offerings',
        accessorKey: 'offerings_count',
        id: 'offerings_count',
    },
    {
        header: 'Students',
        accessorKey: 'total_students',
        id: 'total_students',
    },
    {
        header: 'Avg Attendance',
        accessorKey: 'average_attendance',
        id: 'average_attendance',
    },
    {
        header: 'Avg Grade',
        accessorKey: 'average_grade',
        id: 'average_grade',
    },
    {
        header: 'Pass Rate',
        accessorKey: 'pass_rate',
        id: 'pass_rate',
    },
    {
        header: 'Actions',
        id: 'actions',
        enableSorting: false,
    },
];

const statsData = computed(() => props.statistics.data);
</script>

<template>

    <Head title="Course Statistics" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Course Statistics</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    View aggregated statistics for each unit
                </p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BarChart3 class="h-5 w-5" />
                    Filters
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label>Semester</Label>
                        <Select :model-value="String(filters.semester_id)"
                            @update:model-value="(v) => handleSelectFilter('semester_id', v)">
                            <SelectTrigger>
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.id"
                                    :value="String(semester.id)">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Search</Label>
                        <DebouncedInput :model-value="filters.search" placeholder="Unit code or name..."
                            @update:model-value="handleSearch" />
                    </div>

                    <div class="flex items-end space-y-2">
                        <Button v-if="hasActiveFilters" variant="outline" class="w-full" @click="clearFilters">
                            Clear Filters
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <DataTable :columns="columns" :data="statsData" :initial-sort="currentSort"
                    :initial-direction="currentDirection" enable-server-sorting @sort-change="handleSortChange">
                    <template #cell-no="{ row }">
                        {{ (statistics.current_page - 1) * statistics.per_page + row.index + 1 }}
                    </template>

                    <template #cell-unit="{ row }">
                        <div>
                            <Link
                                :href="route('course-statistics.units.show', { unitId: row.original.id, semester_id: filters.semester_id })"
                                class="block text-blue-500 hover:opacity-80">
                                <div class="flex items-center gap-2">
                                    <BookOpen class="text-primary h-4 w-4" />
                                    <span class="font-mono font-semibold">{{ row.original.unit_code }}</span>
                                </div>
                            </Link>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                {{ row.original.unit_name }}
                            </div>
                            <div class="text-muted-foreground flex items-center gap-2 text-xs">
                                <span v-if="row.original.credit_hours">
                                    • {{ row.original.credit_hours }} credits
                                </span>
                            </div>
                        </div>
                    </template>

                    <template #cell-offerings_count="{ row }">
                        <div class="text-center font-medium">{{ row.original.offerings_count }}</div>
                    </template>

                    <template #cell-total_students="{ row }">
                        <div class="flex items-center justify-center gap-1">
                            <Users class="h-4 w-4 text-blue-600" />
                            <span class="font-bold text-blue-600">{{ row.original.total_students }}</span>
                        </div>
                    </template>

                    <template #cell-average_attendance="{ row }">
                        <div :class="[
                            'text-center font-mono font-semibold',
                            row.original.average_attendance >= 85
                                ? 'text-green-600'
                                : row.original.average_attendance >= 70
                                    ? 'text-yellow-600'
                                    : 'text-red-600',
                        ]">
                            {{ row.original.average_attendance.toFixed(1) }}%
                        </div>
                    </template>

                    <template #cell-average_grade="{ row }">
                        <div :class="[
                            'text-center font-mono font-semibold',
                            row.original.average_grade >= 80
                                ? 'text-green-600'
                                : row.original.average_grade >= 60
                                    ? 'text-yellow-600'
                                    : 'text-red-600',
                        ]">
                            {{ row.original.average_grade > 0 ? row.original.average_grade.toFixed(1) : 'N/A' }}
                        </div>
                    </template>

                    <template #cell-pass_rate="{ row }">
                        <div class="flex items-center justify-center gap-1">
                            <TrendingUp v-if="row.original.pass_rate >= 70" :class="[
                                'h-4 w-4',
                                row.original.pass_rate >= 90 ? 'text-green-600' : 'text-yellow-600',
                            ]" />
                            <TrendingDown v-else class="h-4 w-4 text-red-600" />
                            <span :class="[
                                'font-mono font-semibold',
                                row.original.pass_rate >= 90
                                    ? 'text-green-600'
                                    : row.original.pass_rate >= 70
                                        ? 'text-yellow-600'
                                        : 'text-red-600',
                            ]">
                                {{ row.original.pass_rate.toFixed(1) }}%
                            </span>
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center">
                            <Link
                                :href="route('course-statistics.units.show', { unitId: row.original.id, semester_id: filters.semester_id })">
                                <Button variant="ghost" size="sm">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </CardContent>
        </Card>

        <DataPagination :pagination-data="statistics" @navigate="handlePaginationNavigate"
            @page-size-change="handlePageSizeChange" />
    </div>
</template>
