<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTableFilters } from '@/composables/useFilters';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Eye, Plus } from 'lucide-vue-next';
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
    created_at: string;
    unit?: Unit;
}

const props = defineProps<{
    items: PaginatedResponse<TemplateRow>;
    filters: { search?: string; is_active?: string | null; unit_id?: number | null; per_page?: number };
    units: Unit[];
}>();
console.log(props.items);
const { filters, updateFieldDebounced, updateField, handlePaginationNavigate, handlePageSizeChange } = useTableFilters<{
    search?: string;
    is_active?: string | null;
    unit_id?: number | null;
    per_page?: number;
}>('/syllabus-templates', props.filters, ['items', 'filters']);

const columns = computed<ColumnDef<TemplateRow>[]>(() => [
    {
        accessorKey: 'title',
        header: 'Title',
        cell: ({ row }) => row.original.title,
    },
    {
        accessorKey: 'unit',
        header: 'Unit',
        cell: ({ row }) => `${row.original.unit?.code ?? ''} - ${row.original.unit?.name ?? ''}`.trim(),
    },
    {
        accessorKey: 'unit',
        header: 'Level',
        cell: ({ row }) => row.original.unit?.level ?? '—',
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
    {
        accessorKey: 'delivery_mode',
        header: 'Mode',
        cell: ({ row }) => row.original.delivery_mode ?? '—',
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
                <Button @click="router.get(`/syllabus-templates/create`)"><Plus class="mr-2 h-4 w-4" /> New Template</Button>
            </div>
        </div>
        <div class="flex items-end justify-between gap-4">
            <div class="w-full space-y-2">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm">Search</label>
                        <Input :model-value="filters.search || ''" placeholder="Search by title, version, unit..." @update:model-value="(v) => updateFieldDebounced('search', v)" />
                    </div>
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm">Unit</label>
                        <Select :model-value="filters.unit_id?.toString() ?? ''" @update:model-value="(v) => updateField('unit_id', v ? Number(v) : null)">
                            <SelectTrigger>
                                <SelectValue placeholder="All units" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem v-for="u in units" :key="u.id" :value="u.id.toString()">{{ u.code }} — {{ u.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col">
                        <label class="mb-1 text-sm">Status</label>
                        <Select :model-value="filters.is_active ?? ''" @update:model-value="(v) => updateField('is_active', v || null)">
                            <SelectTrigger>
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <div class="hidden shrink-0"></div>
        </div>

        <DataTable :data="items.data" :columns="columns" :loading="false">
            <template #cell-status="{ row }">
                <div class="flex gap-2">
                    <Badge v-if="row.original.is_default" variant="default">Default</Badge>
                    <Badge :variant="row.original.is_active ? 'default' : 'secondary'">{{ row.original.is_active ? 'Active' : 'Inactive' }}</Badge>
                </div>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-2">
                    <Button variant="ghost" size="sm" @click="router.visit(`/syllabus-templates/${row.original.id}`)"><Eye class="h-4 w-4" /></Button>
                    <Button variant="ghost" size="sm" @click="router.visit(`/syllabus-templates/${row.original.id}/edit`)"><Edit class="h-4 w-4" /></Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :pagination-data="items" item-name="templates" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>

<style scoped></style>
