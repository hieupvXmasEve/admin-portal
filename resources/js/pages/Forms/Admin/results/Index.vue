<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import type { FormTarget, Form } from '@/types/forms';
import type { Semester } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { BarChart3, FileText, Search, X } from 'lucide-vue-next';
import { computed, h } from 'vue';

interface RunWithStats extends FormTarget {
    responses_count: number;
    assignments_count: number;
    form: Form;
    semester: Semester;
    course_code?: string;
    course_name?: string;
    section_code?: string;
    instructor_name?: string;
}

interface ResultFilters {
    search: string;
    semester_id: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    runs: PaginatedResponse<RunWithStats>;
    filters?: Partial<ResultFilters>;
    semesters: Semester[];
}>();

const data = computed(() => props.runs.data);

const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useInertiaFilters<ResultFilters>({
    baseUrl: '/forms/admin/results',
    initialFilters: {
        search: props.filters?.search || '',
        semester_id: props.filters?.semester_id || 'all',
        status: props.filters?.status || 'all',
        sort: props.filters?.sort || null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        search: '',
        semester_id: 'all',
        status: 'all',
        sort: null,
        direction: 'asc',
        per_page: 15,
    },
    only: ['runs', 'filters'],
    debounce: 400,
});

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (status) {
        case 'active': return 'default';
        case 'closed': return 'secondary';
        case 'draft': return 'outline';
        default: return 'outline';
    }
};

const columns: ColumnDef<RunWithStats>[] = [
    {
        header: 'Survey / Course / Section',
        id: 'form_title',
        accessorFn: (row) => row.form?.title,
        enableSorting: true,
        cell: ({ row }) => {
            const run = row.original;
            return h('div', { class: 'flex flex-col' }, [
                h('div', { class: 'flex items-center gap-2' }, [
                    h('span', { class: 'font-bold text-primary' }, run.course_code ? `[${run.course_code}] ${run.course_name}` : run.form.title),
                    run.section_code ? h(Badge, { variant: 'outline', class: 'text-[10px] px-1 py-0 h-4' }, () => run.section_code) : null,
                ]),
                run.course_code ? h('span', { class: 'text-xs text-muted-foreground' }, `${run.form.title} • ${run.instructor_name || 'No instructor'}`) : null,
            ]);
        },
    },
    {
        header: 'Semester',
        id: 'semester',
        accessorFn: (row) => row.semester?.name,
        enableSorting: true,
        cell: ({ row }) => row.original.semester?.name || 'N/A',
    },
    {
        header: 'Responses (Done/Total)',
        id: 'responses_count',
        accessorKey: 'responses_count',
        enableSorting: true,
        cell: ({ row }) => {
            const run = row.original;
            return h('div', { class: 'flex flex-col' }, [
                h('code', { class: 'text-sm font-bold bg-muted px-1.5 py-0.5 rounded w-fit' }, `${run.responses_count} / ${run.assignments_count}`),
                h('div', { class: 'w-24 h-1.5 bg-secondary rounded-full mt-1 overflow-hidden' }, [
                    h('div', {
                        class: 'h-full bg-primary',
                        style: { width: `${(run.responses_count / (run.assignments_count || 1)) * 100}%` }
                    })
                ])
            ]);
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: true,
        cell: ({ row }) => h(Badge, { variant: getStatusBadgeVariant(row.original.status) }, () => row.original.status),
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => h('div', { class: 'flex items-center space-x-2' }, [
            h(Button, {
                variant: 'ghost',
                size: 'sm',
                onClick: () => router.visit(`/forms/admin/results/${row.original.id}/aggregate`)
            }, () => [h(BarChart3, { class: 'w-4 h-4 mr-1' }), 'Aggregate']),
            h(Button, {
                variant: 'ghost',
                size: 'sm',
                onClick: () => router.visit(`/forms/admin/results/${row.original.id}/raw`)
            }, () => [h(FileText, { class: 'w-4 h-4 mr-1' }), 'Raw']),
        ]),
    }
];

</script>

<template>
    <Head title="Survey Results" />

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Survey Results</h1>
    </div>

    <div class="space-y-4 rounded-lg border p-4 bg-card">
        <div class="flex flex-wrap items-center gap-4">
            <div class="min-w-[250px] flex-1">
                <DebouncedInput placeholder="Search surveys, courses, sections or instructors..." :model-value="filters.search" @update:model-value="handleSearch" />
            </div>

            <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                <X class="mr-2 h-4 w-4" />
                Clear Filters
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs font-semibold">Semester</Label>
                <Select :model-value="filters.semester_id" @update:model-value="(v) => handleSelectFilter('semester_id', v, 'all')">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All Semesters" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Semesters</SelectItem>
                        <SelectItem v-for="s in semesters" :key="s.id" :value="s.id.toString()">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs font-semibold">Status</Label>
                <Select :model-value="filters.status" @update:model-value="(v) => handleSelectFilter('status', v, 'all')">
                    <SelectTrigger class="w-32">
                        <SelectValue placeholder="All Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="closed">Closed</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </div>

    <DataTable 
        :data="data" 
        :columns="columns" 
        enable-server-sorting 
        :initial-sort="currentSort" 
        :initial-direction="currentDirection" 
        @sort-change="handleSortChange"
    />

    <DataPagination 
        :pagination-data="runs" 
        item-name="results" 
        @navigate="handlePaginationNavigate" 
        @page-size-change="handlePageSizeChange" 
    />
</template>
