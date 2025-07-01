<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
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
import type { Campus } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Building, Edit, Eye, Plus, Trash2 } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    campuses: PaginatedResponse<Campus>;
}

const props = defineProps<Props>();
console.log(props.campuses);

const data = computed(() => props.campuses.data);

const deleteDialogOpen = ref(false);
const selectedCampus = ref<Campus | null>(null);

const deleteForm = useForm({});

const openDeleteModal = (campus: Campus) => {
    selectedCampus.value = campus;
    deleteDialogOpen.value = true;
};

const closeDeleteModal = () => {
    deleteDialogOpen.value = false;
    selectedCampus.value = null;
};

const submitDelete = () => {
    if (!selectedCampus.value) return;

    deleteForm.delete(route('campuses.destroy', selectedCampus.value.id), {
        onSuccess: () => {
            closeDeleteModal();
            toast.success('Campus deleted successfully');
        },
        onError: (err: any) => {
            closeDeleteModal();
            toast.error('Failed to delete campus', {
                description: err.message || 'An unexpected error occurred.',
            });
        },
    });
};

const goToCreatePage = () => {
    router.visit(systemRoutes.campuses.create());
};

const goToEditPage = (campus: Campus) => {
    router.visit(systemRoutes.campuses.edit(campus.id));
};

const goToViewPage = (campus: Campus) => {
    router.visit(systemRoutes.campuses.show(campus.id));
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['campuses'],
    });
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
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Campuses</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage all campus locations.</p>
        </div>
        <Button size="sm" @click="goToCreatePage">
            <Plus class="mr-2 h-4 w-4" />
            Add Campus
        </Button>
    </div>

    <div class="mt-6">
        <DataTable :data="data" :columns="columns">
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <TooltipProvider :delay-duration="0">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="goToViewPage(row.original)">
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
                                <Button variant="ghost" size="sm" @click="goToEditPage(row.original)">
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit Campus</p>
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
                                <p>Delete Campus</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <DataPagination :pagination-data="campuses" @navigate="handlePaginationNavigate" />

    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Campus</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete the campus
                    <strong>{{ selectedCampus?.name }}</strong
                    >? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeDeleteModal">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="submitDelete" :disabled="deleteForm.processing" class="bg-red-600 hover:bg-red-700">
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete Campus' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
