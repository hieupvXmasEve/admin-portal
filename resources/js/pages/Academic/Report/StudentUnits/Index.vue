<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { Head } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { FileSpreadsheet, Search, X } from 'lucide-vue-next';
import { computed, h } from 'vue';

interface CompletedUnit {
    code: string;
    name: string;
    credits: string;
}

interface StudentCompletedUnitsRow {
    id: number;
    student_id: string;
    full_name: string;
    program: string | null;
    gc: CompletedUnit[];
    major: CompletedUnit[];
    units_count: number;
    credits_earned: string;
    credits_required: string;
}

const props = defineProps<{
    report: PaginatedResponse<StudentCompletedUnitsRow>;
    filters: {
        active: {
            program_id: number | null;
            keyword: string | null;
            sort: string;
            direction: 'asc' | 'desc';
            per_page: number;
        };
        options: {
            programs: { id: number; name: string }[];
        };
    };
}>();

interface StudentCompletedUnitsFilters {
    program_id: string;
    keyword: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const { filters, setFilter, clearAllFilters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, hasActiveFilters, currentSort, currentDirection } =
    useDataTable<StudentCompletedUnitsFilters>({
        baseUrl: route('academic.reports.student-units.index'),
        initialFilters: {
            program_id: props.filters.active.program_id ? String(props.filters.active.program_id) : '',
            keyword: props.filters.active.keyword ?? '',
            sort: props.filters.active.sort,
            direction: props.filters.active.direction,
            per_page: props.filters.active.per_page,
        },
        defaultValues: { program_id: '', keyword: '', sort: 'student_id', direction: 'asc', per_page: 25 },
        only: ['report', 'filters'],
        fieldDebounce: { keyword: 400 },
        immediateFields: ['program_id'],
    });

const data = computed(() => props.report.data);

const displayProgramId = computed(() => filters.program_id || 'all');

const updateProgramFilter = (value: unknown) => setFilter('program_id', value === 'all' ? '' : String(value ?? ''));

const chipCell = (units: CompletedUnit[]) =>
    units.length === 0
        ? h('span', { class: 'text-slate-300' }, '—')
        : h(
              'div',
              { class: 'flex flex-wrap gap-1' },
              units.map((unit) => h(Badge, { key: unit.code, variant: 'outline', title: unit.name }, () => unit.code)),
          );

const columns: ColumnDef<StudentCompletedUnitsRow>[] = [
    { header: 'Student ID', accessorKey: 'student_id' },
    { header: 'Full Name', accessorKey: 'full_name' },
    {
        header: 'Program',
        accessorKey: 'program',
        enableSorting: false,
        cell: ({ row }) => row.original.program ?? h('span', { class: 'text-slate-300' }, '—'),
    },
    {
        header: 'GC Units',
        accessorKey: 'gc',
        enableSorting: false,
        cell: ({ row }) => chipCell(row.original.gc),
    },
    {
        header: 'Major Units',
        accessorKey: 'major',
        enableSorting: false,
        cell: ({ row }) => chipCell(row.original.major),
    },
    { header: 'Units', accessorKey: 'units_count' },
    { header: 'Credits Earned', accessorKey: 'credits_earned' },
    { header: 'Credits Required', accessorKey: 'credits_required', enableSorting: false },
];

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') params.set(key, String(value));
    });

    return `${route('academic.reports.student-units.export')}?${params.toString()}`;
});
</script>

<template>
    <Head title="Student Completed Units" />

    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Student Completed Units</h1>
            <p class="mt-1 text-slate-500">All students with passed units, split by GC / Major.</p>
        </div>
        <Button as="a" :href="exportUrl" class="bg-indigo-600 text-white shadow-md transition-all hover:bg-indigo-700">
            <FileSpreadsheet class="mr-2 h-4 w-4" />
            Export Excel
        </Button>
    </div>

    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <div class="relative">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <DebouncedInput v-model="filters.keyword" @debounced="handleSearch" placeholder="Student ID / Name..." class="pl-9" :debounce="400" />
            </div>
        </div>

        <div class="min-w-[180px]">
            <Select :model-value="displayProgramId" @update:model-value="updateProgramFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All programs" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    <SelectItem v-for="program in props.filters.options.programs" :key="program.id" :value="String(program.id)">{{ program.name }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearAllFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <div class="overflow-x-auto rounded-md border">
        <DataTable :data="data" :columns="columns" :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange" />
    </div>

    <DataPagination :pagination-data="props.report" item-name="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
