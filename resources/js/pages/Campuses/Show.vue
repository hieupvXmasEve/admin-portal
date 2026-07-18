<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useDataTable } from '@/composables/useDataTable';
import { useConfirmDialogStore } from '@/stores/confirmDialog';
import type { PaginatedResponse } from '@/types';
import type { Building, Campus } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { ModalLink } from '@inertiaui/modal-vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, Building2, Edit, MapPin, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { route } from 'ziggy-js';

interface BuildingFilters {
    search: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

interface Props {
    campus: Campus & { buildings_count?: number };
    buildings: PaginatedResponse<Building>;
    filters?: Partial<BuildingFilters>;
}

const props = defineProps<Props>();
const confirmDialog = useConfirmDialogStore();

const { filters, hasActiveFilters, clearAllFilters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, isLoading, currentSort, currentDirection } = useDataTable<BuildingFilters>({
    baseUrl: route('campuses.show', props.campus.id),
    initialFilters: {
        search: props.filters?.search || '',
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'asc',
        search: '',
        sort: null,
    },
    only: ['buildings', 'filters'],
    debounce: 400,
});

const buildingData = computed(() => props.buildings.data);

// Building table columns
const buildingColumns: ColumnDef<Building>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.buildings.current_page;
            const perPage = props.buildings.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Building Code',
        accessorKey: 'code',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-mono font-medium' }, row.original.code),
    },
    {
        header: 'Building Name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.original.name),
    },
    {
        header: 'Description',
        accessorKey: 'description',
        enableSorting: false,
        cell: ({ row }) => {
            const description = row.original.description;
            return h('div', { class: 'max-w-xs truncate text-sm text-gray-600 dark:text-gray-300' }, description || 'No description');
        },
    },
    {
        header: 'Address',
        accessorKey: 'address',
        enableSorting: false,
        cell: ({ row }) => {
            const address = row.original.address;
            return h('div', { class: 'max-w-xs truncate text-sm text-gray-600 dark:text-gray-300' }, address || 'No address');
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

const confirmDeleteBuilding = (building: Building) => {
    confirmDialog.showDialog(
        {
            title: 'Delete Building',
            message: `Are you sure you want to delete "${building.name}"? This action cannot be undone.`,
        },
        {
            onConfirm: () => deleteBuilding(building),
        },
    );
};

const deleteBuilding = (building: Building) => {
    router.delete(systemRoutes.campuses.buildings.destroy(props.campus.id, building.id), {
        preserveScroll: true,
        onSuccess: () => confirmDialog.hideDialog(),
    });
};
</script>

<template>
    <Head :title="`${campus.name} - Campus Details`" />

    <div class="flex flex-col gap-6">
        <!-- Header with back button -->
        <div class="flex items-center gap-4">
            <Button variant="ghost" size="sm" @click="router.visit(systemRoutes.campuses.index())">
                <ArrowLeft class="h-4 w-4" />
                Back to Campuses
            </Button>
            <div class="flex-1">
                <h1 class="text-2xl font-semibold">{{ campus.name }}</h1>
                <p class="text-muted-foreground text-sm">Campus details and building management</p>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Campus Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MapPin class="h-5 w-5" />
                        Campus Information
                    </CardTitle>
                    <CardDescription>Basic information about this campus</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Campus Code</label>
                        <p class="font-mono font-medium">{{ campus.code }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Campus Name</label>
                        <p class="font-medium">{{ campus.name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                        <p class="text-sm">{{ campus.address || 'No address provided' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</label>
                        <p class="text-sm">{{ new Date(campus.created_at).toLocaleDateString() }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Campus Statistics -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Campus Statistics
                    </CardTitle>
                    <CardDescription>Overview of campus facilities and resources</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">Total Buildings</span>
                        <span class="text-lg font-bold">{{ campus.buildings_count || 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">Total Users</span>
                        <span class="text-lg font-bold">{{ campus.users_count || 0 }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Buildings Management Section -->
        <div class="space-y-6">
            <!-- Buildings Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Buildings Management</h3>
                    <p class="text-muted-foreground text-sm">Manage buildings for {{ campus.name }}</p>
                </div>
                <ModalLink
                    :href="systemRoutes.campuses.buildings.create(campus.id)"
                    as="button"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium shadow focus-visible:ring-1 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                >
                    <Plus class="h-4 w-4" />
                    Add Building
                </ModalLink>
            </div>

            <!-- Building Filters -->
            <div class="flex items-center gap-2">
                <div class="w-full max-w-sm">
                    <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" placeholder="Search buildings by name, code, or description..." />
                </div>
                <Button variant="outline" size="sm" @click="clearAllFilters" :disabled="!hasActiveFilters" v-if="hasActiveFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear
                </Button>
            </div>

            <!-- Buildings Table -->
            <DataTable :data="buildingData" :columns="buildingColumns" :loading="isLoading" :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange">
                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-1">
                        <ModalLink :href="systemRoutes.campuses.buildings.edit(campus.id, row.original.id)" as="button" class="hover:bg-accent hover:text-accent-foreground inline-flex h-8 w-8 items-center justify-center rounded-md">
                            <Edit class="h-4 w-4" />
                        </ModalLink>
                        <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-700" @click="confirmDeleteBuilding(row.original)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>

            <!-- Buildings Pagination -->
            <DataPagination :pagination-data="buildings" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
        </div>
    </div>
</template>
