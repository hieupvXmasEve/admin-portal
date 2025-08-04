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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { Edit, Eye, FileSpreadsheet, Plus, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Specialization {
    id: number;
    name: string;
    code: string;
    program_id: number;
    program?: {
        id: number;
        name: string;
        degree_level: string;
    };
    curriculum_versions_count: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface Statistics {
    total_specializations: number;
    active_specializations: number;
    inactive_specializations: number;
    by_program: Record<string, number>;
}

const props = defineProps<{
    specializations: PaginatedResponse<Specialization>;
    filters?: {
        search?: string;
        program_id?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    statistics: Statistics;
    programs: Array<{ id: number; name: string }>;
}>();

const page = usePage();

// Reactive data
const data = computed(() => props.specializations.data);

// Filter state
const filters = ref({
    search: props.filters?.search || '',
    program_id: props.filters?.program_id || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

// Selected rows for bulk actions
const selectedRows = ref<number[]>([]);

// Delete dialog state
const deleteDialogOpen = ref(false);
const specializationToDelete = ref<Specialization | null>(null);

// Bulk delete dialog state
const bulkDeleteDialogOpen = ref(false);

// Permission check function
const can = (permission: string) => {
    const permissions = (page.props as any).permissions || [];
    return permissions.includes(permission);
};

// Action functions
const editSpecialization = (specialization: Specialization) => {
    router.visit(`/specializations/${specialization.id}/edit`);
};

const viewSpecialization = (specialization: Specialization) => {
    router.visit(`/specializations/${specialization.id}`);
};

const deleteSpecialization = (specialization: Specialization) => {
    specializationToDelete.value = specialization;
    deleteDialogOpen.value = true;
};

const navigateToCreate = () => {
    const createUrl = new URL('/specializations/create', window.location.origin);

    // Pass current program filter if exists
    if (filters.value.program_id) {
        createUrl.searchParams.set('program_id', filters.value.program_id);
    }

    router.visit(createUrl.pathname + createUrl.search);
};

const confirmDelete = () => {
    if (specializationToDelete.value) {
        router.delete(`/specializations/${specializationToDelete.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Specialization deleted successfully');
                deleteDialogOpen.value = false;
                specializationToDelete.value = null;
            },
            onError: () => {
                toast.error('Failed to delete specialization');
            },
        });
    }
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.program_id) params.set('program_id', newFilters.program_id);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/specializations${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['specializations', 'filters'],
    });
};

// Debounced filter functions
const debouncedApplyFilters = useDebounceFn((newFilters) => {
    applyFilters(newFilters);
}, 500);

const updateSearchFilter = (value: string | number) => {
    filters.value.search = String(value);
    debouncedApplyFilters(filters.value);
};

const updateProgramFilter = (value: any) => {
    // Handle "all" option by converting it to empty string for the backend
    const programId = value === 'all' ? '' : String(value || '');
    filters.value.program_id = programId;
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        program_id: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    router.visit('/specializations', {
        preserveState: true,
        preserveScroll: true,
        only: ['specializations', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || filters.value.program_id;
});

// Bulk delete functionality
const isBulkDeleting = ref(false);

const confirmBulkDelete = async () => {
    if (selectedRows.value.length === 0) return;

    isBulkDeleting.value = true;

    try {
        await fetch('/api/specializations/bulk-delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                specialization_ids: selectedRows.value,
            }),
        });

        selectedRows.value = [];
        bulkDeleteDialogOpen.value = false;
        toast.success('Specializations deleted successfully');
        router.reload();
    } catch (error) {
        console.error('Bulk delete failed:', error);
        toast.error('Failed to delete specializations');
    } finally {
        isBulkDeleting.value = false;
    }
};

// Export functionality
const isExporting = ref(false);

const exportToExcel = async () => {
    if (isExporting.value) return;

    isExporting.value = true;

    try {
        const params = new URLSearchParams();

        if (filters.value.search) params.set('search', filters.value.search);
        if (filters.value.program_id) params.set('program_id', filters.value.program_id);
        if (filters.value.sort) params.set('sort', filters.value.sort);
        if (filters.value.direction) params.set('direction', filters.value.direction);

        const exportUrl = `/specializations/export/excel/filtered${params.toString() ? '?' + params.toString() : ''}`;

        window.location.href = exportUrl;

        setTimeout(() => {
            toast.success('Export started successfully');
        }, 500);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export specializations');
    } finally {
        isExporting.value = false;
    }
};

// Column definitions
const columns: ColumnDef<Specialization>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.specializations.current_page;
            const perPage = props.specializations.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Code',
        accessorKey: 'code',
        enableSorting: true,
        cell: ({ row }) => {
            const specialization = row.original;
            return h('code', { class: 'bg-gray-100 px-2 py-1 rounded text-sm font-mono' }, specialization.code);
        },
    },
    {
        header: 'Name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => {
            return h('div', { class: 'font-medium' }, row.original.name);
        },
    },
    {
        header: 'Program',
        accessorKey: 'program.name',
        enableSorting: true,
        cell: ({ row }) => {
            const program = row.original.program;
            if (!program) return h('span', { class: 'text-gray-400' }, 'No Program');

            return h('div', { class: 'font-medium text-sm' }, program.name);
        },
    },
    {
        header: 'Curriculum Versions',
        accessorKey: 'curriculum_versions_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.curriculum_versions_count;
            return count > 0
                ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800' }, count)
                : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'Status',
        accessorKey: 'is_active',
        enableSorting: false,
        cell: ({ row }) => {
            const isActive = row.original.is_active;
            return isActive ? h('span', { class: 'text-green-500' }, 'Active') : h('span', { class: 'text-red-500' }, 'Inactive');
        },
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
        preserveState: true,
        preserveScroll: true,
        only: ['specializations'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
</script>

<template>
    <Head title="Specializations" />
    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Specializations</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.total_specializations }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Active Specializations</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.active_specializations }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Inactive Specializations</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.inactive_specializations }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Programs Coverage</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ Object.keys(statistics.by_program).length }}</div>
                <p class="text-muted-foreground text-xs">Programs with specializations</p>
            </CardContent>
        </Card>
    </div>

    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Specializations</h1>
        <div class="flex items-center gap-2">
            <Button @click="exportToExcel" variant="outline" :disabled="isExporting" class="flex items-center gap-2">
                <FileSpreadsheet class="h-4 w-4" />
                {{ isExporting ? 'Exporting...' : 'Export Excel' }}
            </Button>
            <Button @click="router.visit('/specializations/import')" variant="outline" class="flex items-center gap-2">
                <Upload class="h-4 w-4" />
                Import Excel
            </Button>

            <Button v-if="can('create_specialization')" size="sm" @click="navigateToCreate">
                <Plus class="mr-2 h-4 w-4" />
                Add Specialization
            </Button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <div class="relative">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input placeholder="Search specializations..." :model-value="filters.search" @update:model-value="updateSearchFilter" class="pl-9" />
            </div>
        </div>

        <div class="min-w-[180px]">
            <Select :model-value="filters.program_id || 'all'" @update:model-value="updateProgramFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All programs" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                        {{ program.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :loading="false">
        <template #cell-actions="{ row }">
            <div class="flex items-center gap-2">
                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="viewSpecialization(row.original)" title="View specialization">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>View specialization</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider v-if="can('edit_specialization')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="editSpecialization(row.original)" title="Edit specialization">
                                <Edit class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Edit specialization</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider v-if="can('delete_specialization')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="deleteSpecialization(row.original)" title="Delete specialization">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Delete specialization</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination :pagination-data="specializations" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Specialization</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete specialization <strong>{{ specializationToDelete?.name }}</strong
                    >? This action cannot be undone and will permanently remove the specialization from the system.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="deleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" class="bg-red-600 hover:bg-red-700">Delete Specialization</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Bulk Delete Confirmation Dialog -->
    <AlertDialog :open="bulkDeleteDialogOpen" @update:open="bulkDeleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Multiple Specializations</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete <strong>{{ selectedRows.length }}</strong> selected specializations? This action cannot be undone
                    and will permanently remove all selected specializations from the system.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="bulkDeleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmBulkDelete" :disabled="isBulkDeleting" class="bg-red-600 hover:bg-red-700">
                    {{ isBulkDeleting ? 'Deleting...' : 'Delete Specializations' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
