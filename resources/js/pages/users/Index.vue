<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import TableActions from '@/components/TableActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import type { Role, User } from '@/types/User';
import { useDataTable } from '@/composables/useDataTable';
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
        type?: string | null;
        per_page?: number;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}>();

interface Filters {
    search: string;
    role_id: string;
    type: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

const {
    filters,
    setFilter,
    clearFilter,
    clearAllFilters,
    setSort,
    setPage,
    setPerPage,
    apply,
    refresh,
    handleSearch,
    handleSortChange,
    handlePaginationNavigate,
    handlePageSizeChange,
    hasActiveFilters,
    isLoading,
    currentSort,
    currentDirection,
} = useDataTable<Filters>({
    baseUrl: route('identity.users.index'),
    initialFilters: {
        search: props.filters?.search ?? '',
        role_id: props.filters?.role_id?.toString() ?? 'all',
        type: props.filters?.type ?? 'all',
        per_page: props.filters?.per_page ?? 10,
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
    },
    defaultValues: { search: '', role_id: 'all', type: 'all', per_page: 10, sort: null, direction: null },
    only: ['users', 'filters'],
    debounce: 300,
    fieldDebounce: { search: 400 },
});

// Reactive data
const data = computed(() => props.users.data);

// User type options
const userTypeOptions = [
    { value: 'all', label: 'All Types' },
    { value: 'staff', label: 'Staff' },
    { value: 'student', label: 'Student' },
    { value: 'lecturer', label: 'Lecturer' },
    { value: 'parent', label: 'Parent' },
];

// Edit user function
const editUser = (user: User) => {
    router.visit(route('identity.users.edit', user.id));
};

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
        if (filters.value.type && filters.value.type !== 'all') params.set('type', filters.value.type);

        // Note: This will need to be updated to use the new export routes
        const exportUrl = `/users/export/excel/filtered${params.toString() ? '?' + params.toString() : ''}`;

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
</script>

<template>
    <Head title="Users" />
    <!-- Header with Add User Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Users</h1>
        <div class="flex items-center gap-2">
            <Button @click="exportToExcel" variant="outline" :disabled="isExporting" class="flex items-center gap-2">
                <FileSpreadsheet class="h-4 w-4" />
                {{ isExporting ? 'Exporting...' : 'Export Excel' }}
            </Button>
            <Button @click="router.visit('/users/import')" variant="outline" class="flex items-center gap-2">
                <Upload class="h-4 w-4" />
                Import Excel
            </Button>
            <Button @click="router.visit(route('identity.users.create'))" class="flex items-center gap-2">
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
                <Select :model-value="filters.role_id" @update:model-value="(v) => setFilter('role_id', v)">
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

            <!-- User Type Filter -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Type</Label>
                <Select :model-value="filters.type" @update:model-value="(v) => setFilter('type', v)">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="All types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="type in userTypeOptions" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Clear Filters Button -->
            <div class="flex flex-col gap-1">
                <Label class="text-xs text-transparent">Clear</Label>
                <Button variant="outline" size="default" @click="clearAllFilters" :disabled="!hasActiveFilters" class="flex items-center gap-2">
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
                <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="clearFilter('search')">
                    <X class="h-3 w-3" />
                </Button>
            </div>

            <div v-if="filters.role_id !== 'all'" class="bg-secondary flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                <span>Role: "{{ roles.find(r => r.id.toString() === filters.role_id)?.name }}"</span>
                <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="clearFilter('role_id')">
                    <X class="h-3 w-3" />
                </Button>
            </div>

            <div v-if="filters.type !== 'all'" class="bg-secondary flex items-center gap-1 rounded-md px-2 py-1 text-sm">
                <span>Type: "{{ userTypeOptions.find(t => t.value === filters.type)?.label }}"</span>
                <Button variant="ghost" size="icon" class="h-4 w-4 p-0" @click="clearFilter('type')">
                    <X class="h-3 w-3" />
                </Button>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <DataTable 
        :data="data" 
        :columns="columns" 
        :show-column-toggle="false"
        :initial-sort="currentSort ?? undefined"
        :initial-direction="currentDirection ?? undefined"
        @sort-change="handleSortChange"
    >
        <template #cell-roles="{ row }">
            <div class="flex flex-wrap gap-1">
                <Badge v-for="role in row.original.campus_roles" :key="role.id" variant="secondary">
                    {{ role.name }}
                </Badge>
                <span v-if="!row.original.campus_roles?.length" class="text-muted-foreground text-sm">No roles</span>
            </div>
        </template>
        <template #cell-actions="{ row }">
            <TableActions @edit="editUser(row.original)">
                <template #default>
                    <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="router.visit(route('identity.users.show', row.original.id))"
                                    class="cursor-pointer"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View Details</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </template>
            </TableActions>
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination 
        :pagination-data="users" 
        item-name="users" 
        @navigate="handlePaginationNavigate" 
        @page-size-change="handlePageSizeChange" 
    />
</template>