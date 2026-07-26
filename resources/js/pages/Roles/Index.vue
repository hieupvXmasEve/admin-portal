<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent } from '@/components/ui/tooltip';
import TooltipProvider from '@/components/ui/tooltip/TooltipProvider.vue';
import TooltipTrigger from '@/components/ui/tooltip/TooltipTrigger.vue';
import { useGlobalConfirmDialog } from '@/composables';
import { ROLE_ROUTE_NAMES } from '@/constants';
import type { PaginatedResponse } from '@/types';
import type { Role } from '@/types/Role';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    roles: PaginatedResponse<Role>;
}>();
const confirmDialog = useGlobalConfirmDialog();

// Reactive data
const data = computed(() => props.roles.data);

// Edit role function
const editRole = (role: Role) => {
    router.visit(`/roles/${role.id}/edit`);
};

// Column definitions
const columns: ColumnDef<Role>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.roles.current_page;
            const perPage = props.roles.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Name',
        accessorKey: 'name',
        enableSorting: true,
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions',
    },
];

// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: false,
        preserveScroll: true,
        only: ['roles'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', pageSize.toString());
    params.delete('page'); // Reset to first page when changing page size

    const url = `/roles?${params.toString()}`;
    router.visit(url, {
        preserveState: false,
        preserveScroll: true,
        only: ['roles'],
    });
};

// Delete role function
const deleteRole = (role: Role) => {
    confirmDialog.confirmDelete(role.name, 'role', () => {
        router.delete(route(ROLE_ROUTE_NAMES.DESTROY, role.id), {
            onSuccess: () => {
                console.log('Role deleted successfully');
            },
        });
    });
};
</script>

<template>
    <Head title="List Roles" />
    <!-- Header with Add Role Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Roles</h1>
        <Button @click="router.visit('/roles/create')" class="flex items-center gap-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Role
        </Button>
    </div>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns">
        <template #cell-actions="{ row }">
            <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button variant="ghost" size="sm" @click="editRole(row.original)" title="Edit unit">
                            <Edit class="h-4 w-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Edit role</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button variant="ghost" size="sm" @click="deleteRole(row.original)" title="Delete unit">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Delete unit</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination :pagination-data="roles" item-name="roles" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
