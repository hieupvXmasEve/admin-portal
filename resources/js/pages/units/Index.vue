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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { Edit, Eye, FileSpreadsheet, Plus, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    prerequisites_count: number;
    prerequisite_conditions_count: number;
    equivalent_units_count: number;
    curriculum_units_count: number;
    syllabus_count: number;
    created_at: string;
    updated_at: string;
}

interface Statistics {
    total_units: number;
    units_with_prerequisites: number;
    units_with_equivalents: number;
    avg_credit_points: number;
}

const props = defineProps<{
    units: PaginatedResponse<Unit>;
    filters?: {
        search?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    statistics: Statistics;
}>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Units',
        href: '/units',
    },
];
console.log(props.units);

// Reactive data
const data = computed(() => props.units.data);

// Filter state - Initialize with props or defaults (like Users page)
const filters = ref({
    search: props.filters?.search || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

// Selected rows for bulk actions
const selectedRows = ref<number[]>([]);

// Delete dialog state
const deleteDialogOpen = ref(false);
const unitToDelete = ref<Unit | null>(null);

// Bulk delete dialog state
const bulkDeleteDialogOpen = ref(false);

// Edit unit function
const editUnit = (unit: Unit) => {
    router.visit(`/units/edit/${unit.id}`);
};

// View unit function
const viewUnit = (unit: Unit) => {
    router.visit(`/units/${unit.id}`);
};

// Delete unit function
const deleteUnit = (unit: Unit) => {
    unitToDelete.value = unit;
    deleteDialogOpen.value = true;
};

// Confirm delete function
const confirmDelete = () => {
    if (unitToDelete.value) {
        router.delete(`/units/${unitToDelete.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Unit deleted successfully');
                deleteDialogOpen.value = false;
                unitToDelete.value = null;
            },
            onError: () => {
                toast.error('Failed to delete unit');
            },
        });
    }
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/units${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['units', 'filters'],
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

const clearFilters = () => {
    filters.value = {
        search: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    router.visit('/units', {
        preserveState: true,
        preserveScroll: true,
        only: ['units', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search;
});

// Bulk delete functionality
const isBulkDeleting = ref(false);

// const bulkDelete = async () => {
//     if (selectedRows.value.length === 0) return;
//     bulkDeleteDialogOpen.value = true;
// };

const confirmBulkDelete = async () => {
    if (selectedRows.value.length === 0) return;

    isBulkDeleting.value = true;

    try {
        await fetch('/api/units/bulk-delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                unit_ids: selectedRows.value,
            }),
        });

        selectedRows.value = [];
        bulkDeleteDialogOpen.value = false;
        toast.success('Units deleted successfully');
        // Refresh the page
        router.reload();
    } catch (error) {
        console.error('Bulk delete failed:', error);
        toast.error('Failed to delete units');
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
        // Build export URL with current filters
        const params = new URLSearchParams();

        if (filters.value.search) params.set('search', filters.value.search);
        if (filters.value.sort) params.set('sort', filters.value.sort);
        if (filters.value.direction) params.set('direction', filters.value.direction);

        const exportUrl = `/units/export/excel/filtered${params.toString() ? '?' + params.toString() : ''}`;

        // Use window.location for file downloads to trigger browser download
        window.location.href = exportUrl;

        // Show success message after a short delay
        setTimeout(() => {
            toast.success('Export started successfully');
        }, 500);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export units');
    } finally {
        isExporting.value = false;
    }
};

// Column definitions
const columns: ColumnDef<Unit>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.units.current_page;
            const perPage = props.units.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Code',
        accessorKey: 'code',
        enableSorting: true,
        cell: ({ row }) => {
            const unit = row.original;
            return h('code', { class: 'bg-gray-100 px-2 py-1 rounded text-sm font-mono' }, unit.code);
        },
    },
    {
        header: 'Name',
        accessorKey: 'name',
        enableSorting: true,
    },
    {
        header: 'Credit Points',
        accessorKey: 'credit_points',
        enableSorting: true,
        cell: ({ row }) => {
            return h('span', { class: 'font-medium' }, row.original.credit_points);
        },
    },
    {
        header: 'Prerequisites',
        accessorKey: 'prerequisite_conditions_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.prerequisite_conditions_count;
            return count > 0
                ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800' }, count)
                : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'Equivalents',
        accessorKey: 'equivalent_units_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.equivalent_units_count;
            return count > 0
                ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800' }, count)
                : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'In Curricula',
        accessorKey: 'curriculum_units_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.curriculum_units_count;
            return count > 0
                ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-800' }, count)
                : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'Syllabus',
        accessorKey: 'syllabus_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.syllabus_count;
            return count > 0
                ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-800' }, count)
                : h('span', { class: 'text-gray-400' }, 'None');
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
        only: ['units'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
</script>

<template>
    <Head title="Units" />
    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Units</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.total_units }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">With Prerequisites</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.units_with_prerequisites }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">With Equivalents</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.units_with_equivalents }}</div>
            </CardContent>
        </Card>
    </div>

    <!-- Header with Add Unit Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Units</h1>
        <div class="flex items-center gap-2">
            <!-- <Button v-if="selectedRows.length > 0" variant="destructive" size="sm" :disabled="isBulkDeleting" @click="bulkDelete">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete Selected ({{ selectedRows.length }})
                    </Button> -->
            <Button @click="exportToExcel" variant="outline" :disabled="isExporting" class="flex items-center gap-2">
                <FileSpreadsheet class="h-4 w-4" />
                {{ isExporting ? 'Exporting...' : 'Export Excel' }}
            </Button>
            <Button
                @click="
                    () => {
                        console.log('Attempting to navigate to /units/import');
                        router.visit('/units/import', {
                            onError: (errors) => {
                                console.error('Navigation error:', errors);
                                toast.error('Failed to navigate to import page');
                            },
                            onSuccess: () => {
                                console.log('Navigation successful');
                            },
                        });
                    }
                "
                variant="outline"
                class="flex items-center gap-2"
            >
                <Upload class="h-4 w-4" />
                Import Excel
            </Button>

            <Button size="sm" @click="router.visit('/units/create')">
                <Plus class="mr-2 h-4 w-4" />
                Add Unit
            </Button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg">
        <div class="min-w-[200px] flex-1">
            <div class="relative">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input placeholder="Search units..." :model-value="filters.search" @update:model-value="updateSearchFilter" class="pl-9" />
            </div>
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
                            <Button variant="ghost" size="sm" @click="viewUnit(row.original)" title="View unit">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>View unit</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="editUnit(row.original)" title="Edit unit">
                                <Edit class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Edit unit</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="deleteUnit(row.original)" title="Delete unit">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Delete unit</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination :pagination-data="units" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Unit</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete unit <strong>{{ unitToDelete?.code }}</strong
                    >? This action cannot be undone and will permanently remove the unit from the system.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="deleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" class="bg-red-600 hover:bg-red-700"> Delete Unit </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Bulk Delete Confirmation Dialog -->
    <AlertDialog :open="bulkDeleteDialogOpen" @update:open="bulkDeleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Multiple Units</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete <strong>{{ selectedRows.length }}</strong> selected units? This action cannot be undone and will
                    permanently remove all selected units from the system.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="bulkDeleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmBulkDelete" :disabled="isBulkDeleting" class="bg-red-600 hover:bg-red-700">
                    {{ isBulkDeleting ? 'Deleting...' : 'Delete Units' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
