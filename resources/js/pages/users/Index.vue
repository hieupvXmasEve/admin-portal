<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import TableActions from '@/components/TableActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import type { Role, User } from '@/types/User';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { FileSpreadsheet, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    users: PaginatedResponse<User>;
    roles: Role[];
    filters?: {
        search?: string;
        role_id?: number | null;
    };
}>();

// Reactive data
const data = computed(() => props.users.data);

// Filter state - khởi tạo từ props
const filters = ref({
    search: props.filters?.search || '',
    role_id: props.filters?.role_id?.toString() || 'all',
});

// Edit user function
const editUser = (user: User) => {
    router.visit(systemRoutes.users.edit(user.id));
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    // Add filters to URL params
    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.role_id && newFilters.role_id !== 'all') params.set('role_id', newFilters.role_id);

    const url = `${systemRoutes.users.index()}${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters'],
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        role_id: 'all',
    };
    router.visit(systemRoutes.users.index(), {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || filters.value.role_id !== 'all';
});

// Export functionality
const isExporting = ref(false);

const exportToExcel = async () => {
    if (isExporting.value) return;

    isExporting.value = true;

    try {
        // Build export URL with current filters
        const params = new URLSearchParams();

        if (filters.value.search) params.set('search', filters.value.search);
        if (filters.value.role_id && filters.value.role_id !== 'all') params.set('role_id', filters.value.role_id);

        const exportUrl = `${systemRoutes.users.exportFiltered()}${params.toString() ? '?' + params.toString() : ''}`;

        // Create a temporary link to trigger download
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = `users_export_${new Date().toISOString().split('T')[0]}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (error) {
        console.error('Export failed:', error);
        // You can add toast notification here if needed
    } finally {
        isExporting.value = false;
    }
};

// Column definitions
const columns: ColumnDef<User>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.users.current_page;
            const perPage = props.users.per_page;
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
        accessorKey: 'email',
        header: 'Email',
        enableSorting: true,
    },
    {
        header: 'Roles',
        id: 'roles',
        enableSorting: false,
        cell: 'roles',
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
    console.log(url);

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', pageSize.toString());
    params.delete('page'); // Reset to first page when changing page size

    const url = `${systemRoutes.users.index()}?${params.toString()}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['users', 'filters'],
    });
};
</script>

<template>
    <Head title="List Users" />
    <!-- Header with Add User Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Users</h1>
        <div class="flex items-center gap-2">
            <Button @click="exportToExcel" variant="outline" :disabled="isExporting" class="flex items-center gap-2">
                <FileSpreadsheet class="h-4 w-4" />
                {{ isExporting ? 'Exporting...' : 'Export Excel' }}
            </Button>
            <Button @click="router.visit(systemRoutes.users.import())" variant="outline" class="flex items-center gap-2">
                <Upload class="h-4 w-4" />
                Import Excel
            </Button>
            <Button @click="router.visit(systemRoutes.users.create())" class="flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add User
            </Button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="space-y-4">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Search -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Search</Label>
                <DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" placeholder="Search by name or email..." class="w-64" />
            </div>

            <!-- Role Filter -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Role</Label>
                <Select v-model="filters.role_id" @update:model-value="applyFilters(filters)">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All roles" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All roles</SelectItem>
                        <SelectItem v-for="role in roles" :key="role.id" :value="role.id.toString()">
                            {{ role.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Clear Filters Button -->
            <div class="flex flex-col gap-1">
                <Label class="text-xs text-transparent">Clear</Label>
                <Button variant="outline" size="default" @click="clearFilters" :disabled="!hasActiveFilters" class="flex items-center gap-2">
                    <X class="h-4 w-4" />
                    Clear
                </Button>
            </div>
        </div>

        <!-- Active Filters Display -->
        <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2">
            <span class="text-muted-foreground text-sm">Active filters:</span>

            <div v-if="filters.search" class="bg-secondary flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                <span>Search: "{{ filters.search }}"</span>
                <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="handleSearch('')">
                    <X class="h-3 w-3" />
                </Button>
            </div>

            <div v-if="filters.role_id !== 'all'" class="bg-secondary flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                <span>Role: "{{ roles.find(r => r.id.toString() === filters.role_id)?.name }}"</span>
                <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="filters.role_id = 'all'; applyFilters(filters)">
                    <X class="h-3 w-3" />
                </Button>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :show-column-toggle="false">
        <template #cell-roles="{ row }">
            <div class="flex flex-wrap gap-1">
                <Badge v-for="role in row.original.campus_roles" :key="role.id" variant="secondary">
                    {{ role.name }}
                </Badge>
                <span v-if="!row.original.campus_roles?.length" class="text-muted-foreground text-sm">No roles</span>
            </div>
        </template>
        <template #cell-actions="{ row }">
            <TableActions @edit="editUser(row.original)" />
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination :pagination-data="users" item-name="users" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
