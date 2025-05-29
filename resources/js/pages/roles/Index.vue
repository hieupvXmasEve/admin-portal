<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import TableActions from '@/components/TableActions.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';
import type { Role } from '@/types/Role';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed } from 'vue';

const props = defineProps<{
    roles: PaginatedResponse<Role>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'List Roles',
        href: '/roles',
    },
];

// Reactive data
const data = computed(() => props.roles.data);

// Edit role function
const editRole = (role: Role) => {
    router.visit(`/roles/edit/${role.id}`);
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
</script>

<template>
    <Head title="List Roles" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
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
                    <TableActions @edit="editRole(row.original)" />
                </template>
            </DataTable>

            <!-- Pagination -->
            <DataPagination
                :pagination-data="roles"
                item-name="roles"
                @navigate="handlePaginationNavigate"
                @page-size-change="handlePageSizeChange"
            />
        </div>
    </AppLayout>
</template>
