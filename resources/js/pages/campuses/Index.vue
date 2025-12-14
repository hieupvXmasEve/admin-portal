<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import type { Campus } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Building, Edit, Eye, Plus } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { route } from 'ziggy-js';

interface CampusFilters {
    search: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

interface Props {
    campuses: PaginatedResponse<Campus>;
    filters?: Partial<CampusFilters>;
}

const props = defineProps<Props>();

const { filters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange } = useInertiaFilters<CampusFilters>({
    baseUrl: route('campuses.index'),
    initialFilters: {
        search: props.filters?.search || '',
        sort: props.filters?.sort || null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'asc',
        search: '',
        sort: null,
    },
    only: ['campuses', 'filters'],
    debounce: 400,
});

const data = computed(() => props.campuses.data);

const goToCreatePage = () => {
    router.visit(systemRoutes.campuses.create());
};

const goToEditPage = (campus: Campus) => {
    router.visit(systemRoutes.campuses.edit(campus.id));
};

const goToViewPage = (campus: Campus) => {
    router.visit(systemRoutes.campuses.show(campus.id));
};

// Column definitions
const columns: ColumnDef<Campus>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.campuses.current_page;
            const perPage = props.campuses.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Campus Code',
        accessorKey: 'code',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-mono font-medium' }, row.original.code),
    },
    {
        header: 'Campus Name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.original.name),
    },
    {
        header: 'Address',
        accessorKey: 'address',
        enableSorting: false,
        cell: ({ row }) => {
            const address = row.original.address;
            return h(
                'div',
                {
                    class: 'max-w-xs truncate text-sm text-gray-600 dark:text-gray-300',
                },
                address || 'No address',
            );
        },
    },
    {
        header: 'Buildings',
        id: 'buildings_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.buildings_count || 0;
            return h(
                'div',
                {
                    class: 'flex items-center gap-1 text-sm',
                },
                [h(Building, { class: 'h-4 w-4' }), count.toString()],
            );
        },
    },
    {
        header: 'Created',
        accessorKey: 'created_at',
        enableSorting: true,
        cell: ({ row }) => {
            const date = new Date(row.original.created_at);
            return h('div', { class: 'text-sm text-gray-600 dark:text-gray-300' }, date.toLocaleDateString());
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: 'actions',
    },
];


</script>

<template>
    <Head title="Campuses" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Campuses</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage all campus locations.</p>
        </div>
        <Button size="sm" @click="goToCreatePage">
            <Plus class="mr-2 h-4 w-4" />
            Add Campus
        </Button>
    </div>

    <div class="mt-6 flex flex-col gap-4">
        <!-- Filters -->
        <div class="flex items-center gap-2">
            <div class="w-full max-w-sm">
                <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" placeholder="Search campuses..." />
            </div>
        </div>

        <DataTable 
            :data="data" 
            :columns="columns" 
            :initial-sort="filters.sort || undefined" 
            :initial-direction="filters.direction || undefined" 
            @sort-change="handleSortChange"
        >
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-primary" @click="goToViewPage(row.original)">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View Campus</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-primary" @click="goToEditPage(row.original)">
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit Campus</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="campuses" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
