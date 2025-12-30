<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Eye, Plus, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface Unit {
    id: number;
    code: string;
    name: string;
    level: number;
}

interface TemplateRow {
    id: number;
    title: string;
    version: string | null;
    is_default: boolean;
    is_active: boolean;
    delivery_mode: string | null;
    min_attendance_threshold: string | number;
    min_grade_threshold: string | number;
    created_at: string;
    unit?: Unit;
}

interface SyllabusTemplateFilters {
    search: string;
    unit_id: string;
    is_active: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
}

const props = defineProps<{
    items: PaginatedResponse<TemplateRow>;
    filters: Partial<SyllabusTemplateFilters>;
    units: Unit[];
}>();

const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useInertiaFilters<SyllabusTemplateFilters>({
    baseUrl: route('syllabus_templates.index'),
    initialFilters: {
        search: (typeof props.filters?.search === 'string' ? props.filters.search : '') || '',
        unit_id: (typeof props.filters?.unit_id === 'string' ? props.filters.unit_id : 'all') || 'all',
        is_active: (typeof props.filters?.is_active === 'string' ? props.filters.is_active : 'all') || 'all',
        sort: (typeof props.filters?.sort === 'string' ? props.filters.sort : 'created_at') || 'created_at',
        direction: (props.filters?.direction as 'asc' | 'desc') || 'desc',
        per_page: props.filters?.per_page || 10,
        page: props.items.current_page || 1,
    },
    defaultValues: {
        unit_id: 'all',
        is_active: 'all',
        per_page: 10,
        direction: 'desc',
        sort: 'created_at',
    },
    only: ['items', 'filters'],
});

const columns = computed<ColumnDef<TemplateRow>[]>(() => [
    {
        accessorKey: 'title',
        header: 'Title',
        cell: ({ row }) => row.original.title,
        enableSorting: true,
    },
    {
        accessorKey: 'unit',
        header: 'Unit',
        cell: ({ row }) => `${row.original.unit?.code ?? ''} - ${row.original.unit?.name ?? ''}`.trim(),
        enableSorting: false,
    },
    {
        accessorKey: 'unit',
        header: 'Level',
        cell: ({ row }) => row.original.unit?.level ?? '—',
        enableSorting: false,
    },
    {
        accessorKey: 'version',
        header: 'Version',
        cell: ({ row }) => row.original.version ?? '—',
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: 'status',
    },
    // {
    //     accessorKey: 'delivery_mode',
    //     header: 'Mode',
    //     cell: ({ row }) => row.original.delivery_mode ?? '—',
    // },
    {
        accessorKey: 'min_attendance_threshold',
        header: 'Min Attend',
        cell: ({ row }) => `${Number(row.original.min_attendance_threshold)}%`,
        enableSorting: true,
    },
    {
        accessorKey: 'min_grade_threshold',
        header: 'Min Grade',
        cell: ({ row }) => Number(row.original.min_grade_threshold),
        enableSorting: true,
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        cell: ({ row }) => new Date(row.original.created_at).toLocaleDateString(),
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: 'actions',
    },
]);
</script>

<template>

    <Head title="Syllabus Templates" />
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Syllabus Templates</h1>
            <div class="flex gap-2">
                <Button @click="router.get(`/syllabus-templates/create`)">
                    <Plus class="mr-2 h-4 w-4" /> New Template
                </Button>
            </div>
        </div>

        <div class="flex items-end justify-between gap-4">
            <div class="w-full space-y-2">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm font-medium">Search</label>
                        <Input :model-value="filters.search" placeholder="Search by title, version, unit..."
                            @update:model-value="handleSearch" />
                    </div>
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm font-medium">Unit</label>
                        <Select :model-value="filters.unit_id"
                            @update:model-value="(v) => handleSelectFilter('unit_id', v)">
                            <SelectTrigger>
                                <SelectValue placeholder="All units" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Units</SelectItem>
                                <SelectItem v-for="u in units" :key="u.id" :value="String(u.id)">{{ u.code }} — {{
                                    u.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm font-medium">Status</label>
                        <Select :model-value="filters.is_active"
                            @update:model-value="(v) => handleSelectFilter('is_active', v)">
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <div v-if="hasActiveFilters" class="shrink-0 pb-0.5">
                <Button variant="ghost" size="sm" class="text-xs text-muted-foreground" @click="clearFilters">
                    <X class="mr-1 h-3 w-3" /> Clear filters
                </Button>
            </div>
        </div>

        <DataTable :data="items.data" :columns="columns" :initial-sort="currentSort"
            :initial-direction="currentDirection" enable-server-sorting @sort-change="handleSortChange">
            <template #cell-status="{ row }">
                <div class="flex gap-2">
                    <Badge v-if="row.original.is_default" variant="default">Default</Badge>
                    <Badge :variant="row.original.is_active ? 'default' : 'secondary'">{{ row.original.is_active ?
                        'Active' : 'Inactive' }}</Badge>
                </div>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-left gap-2">
                    <Button variant="ghost" size="sm" @click="router.visit(`/syllabus-templates/${row.original.id}`)">
                        <Eye class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="sm"
                        @click="router.visit(`/syllabus-templates/${row.original.id}/edit`)">
                        <Edit class="h-4 w-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :pagination-data="items" item-name="templates" @navigate="handlePaginationNavigate"
            @page-size-change="handlePageSizeChange" />
    </div>
</template>

<style scoped></style>
