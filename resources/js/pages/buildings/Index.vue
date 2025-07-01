<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import type { Building } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Plus, School, Trash2, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    buildings: PaginatedResponse<Building>;
    filters?: {
        search?: string;
    };
}>();

const data = computed(() => props.buildings.data);

const filters = ref({
    search: props.filters?.search || '',
});

const deleteDialogOpen = ref(false);
const selectedBuilding = ref<Building | null>(null);

const deleteForm = useForm({});

const openDeleteModal = (building: Building) => {
    selectedBuilding.value = building;
    deleteDialogOpen.value = true;
};

const closeDeleteModal = () => {
    deleteDialogOpen.value = false;
    selectedBuilding.value = null;
};

const submitDelete = () => {
    if (!selectedBuilding.value) return;

    deleteForm.delete(route('buildings.destroy', selectedBuilding.value.id), {
        onSuccess: () => {
            closeDeleteModal();
            toast.success('Building deleted successfully');
        },
        onError: (err: any) => {
            closeDeleteModal();
            toast.error('Failed to delete building', {
                description: err.message || 'An unexpected error occurred.',
            });
        },
    });
};

const goToCreatePage = () => {
    router.visit(systemRoutes.buildings.create());
};

const goToEditPage = (building: Building) => {
    router.visit(systemRoutes.buildings.edit(building.id));
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    router.visit(route('buildings.index'), {
        data: { search: filters.value.search },
        preserveState: true,
        preserveScroll: true,
        only: ['buildings', 'filters'],
    });
};

const clearFilters = () => {
    filters.value.search = '';
    router.visit(route('buildings.index'), {
        preserveState: true,
        preserveScroll: true,
        only: ['buildings', 'filters'],
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['buildings'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search;
});

const columns: ColumnDef<Building>[] = [
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
        header: 'Building Name',
        accessorKey: 'name',
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.original.name),
    },
    {
        header: 'Code',
        accessorKey: 'code',
        cell: ({ row }) => h('div', { class: 'font-mono' }, row.original.code),
    },
    {
        header: 'Campus',
        accessorKey: 'campus.name',
        cell: ({ row }) => {
            const campus = row.original.campus;
            return campus
                ? h('div', { class: 'flex items-center gap-2' }, [h(School, { class: 'h-4 w-4 text-gray-500' }), campus.name])
                : h('span', { class: 'text-gray-400' }, 'N/A');
        },
    },
    {
        header: 'Type',
        accessorKey: 'type',
        cell: ({ row }) => {
            const type = row.original.type;
            return h('div', { class: 'capitalize' }, type);
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: 'actions',
    },
];
</script>

<template>
    <Head title="Buildings" />
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Buildings</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage all buildings across campuses.</p>
        </div>
        <Button size="sm" @click="goToCreatePage">
            <Plus class="mr-2 h-4 w-4" />
            Add Building
        </Button>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <DebouncedInput v-model="filters.search" placeholder="Search by name or code..." @debounced="handleSearch" />
        </div>
        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <div class="mt-6">
        <DataTable :data="data" :columns="columns">
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="goToEditPage(row.original)">
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit Building</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="openDeleteModal(row.original)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Delete Building</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="buildings" @navigate="handlePaginationNavigate" />

    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Building</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete the building
                    <strong>{{ selectedBuilding?.name }}</strong
                    >? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeDeleteModal">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="submitDelete" :disabled="deleteForm.processing" class="bg-red-600 hover:bg-red-700">
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete Building' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
