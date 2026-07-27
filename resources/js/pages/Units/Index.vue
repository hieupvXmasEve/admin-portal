<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useApi } from '@/composables/useApiRequest';
import { useDataTable } from '@/composables/useDataTable';
import { useModuleNavigation } from '@/composables/useModuleNavigation';
import type { PaginatedResponse } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Eye, FileSpreadsheet, Plus, Trash2, Upload } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    level: number;
    base_fee: number | null;
    retake_fee: number | null;
    unit_type: string;
    prerequisites_count: number;
    prerequisite_conditions_count: number;
    equivalent_units_count: number;
    curriculum_units_count: number;
    syllabus_templates_count: number;
    created_at: string;
    updated_at: string;
}

interface Statistics {
    total_units: number;
    units_with_prerequisites: number;
    units_with_equivalents: number;
    avg_credit_points: number;
}

interface UnitsFilters {
    search: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
    type: string;
    level: string;
}

const props = defineProps<{
    units: PaginatedResponse<Unit>;
    filters?: UnitsFilters;
    statistics: Statistics;
}>();

// Reactive data
const data = computed(() => props.units.data);

const { filters, setFilter, clearAllFilters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, hasActiveFilters, isLoading, currentSort, currentDirection } = useDataTable<UnitsFilters>({
    baseUrl: route('units.index'),
    initialFilters: {
        search: props.filters?.search ?? '',
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,
        direction: props.filters?.direction ?? null,
        per_page: props.filters?.per_page ?? 15,
        page: 1,
        type: props.filters?.type ?? '',
        level: props.filters?.level ?? '',
    },
    defaultValues: {
        search: '',
        sort: null,
        direction: null,
        per_page: 15,
        page: 1,
        type: '',
        level: '',
    },
    only: ['units', 'filters'],
    debounce: 300,
    fieldDebounce: { search: 400 },
    immediateFields: ['type', 'level'],
});

// Module navigation với return param
const { getLinkUrlWithReturn } = useModuleNavigation({
    moduleIndexRoute: 'units.index',
});
// Selected rows for bulk actions
const selectedRows = ref<number[]>([]);
const api = useApi();

// Delete dialog state
const deleteDialogOpen = ref(false);
const unitToDelete = ref<Unit | null>(null);

// Bulk delete dialog state
const bulkDeleteDialogOpen = ref(false);

// Edit unit function
const editUnit = (unit: Unit) => {
    const editUrl = getLinkUrlWithReturn('units.edit', { unit: unit.id });
    router.visit(editUrl, { preserveScroll: true, preserveState: true });
};

// View unit function
const viewUnit = (unit: Unit) => {
    const showUrl = getLinkUrlWithReturn('units.show', { unit: unit.id });
    router.visit(showUrl, { preserveScroll: true, preserveState: true });
};

// Delete unit function
const deleteUnit = (unit: Unit) => {
    unitToDelete.value = unit;
    deleteDialogOpen.value = true;
};

// Confirm delete function
const confirmDelete = () => {
    if (unitToDelete.value) {
        router.delete(route('units.destroy', { unit: unitToDelete.value.id }), {
            preserveScroll: true,
            only: ['units', 'statistics'],
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

// Unit type options
const unitTypeOptions = [
    { value: 'all', label: 'All Types' },
    { value: 'general', label: 'General' },
    { value: 'egc', label: 'EGC' },
    { value: 'semi', label: 'Semiconductor' },
    { value: 'ai', label: 'AI' },
    { value: 'mkt', label: 'Marketing' },
    { value: 'ba', label: 'Business Admin' },
    { value: 'cs', label: 'CS' },
    { value: 'ee', label: 'EE' },
    { value: 'me', label: 'ME' },
    { value: 'fin', label: 'Finance' },
];

// Level options
const levelOptions = [
    { value: 'all', label: 'All Levels' },
    { value: '0', label: 'Level 0' },
    { value: '1', label: 'Level 1' },
    { value: '2', label: 'Level 2' },
    { value: '3', label: 'Level 3' },
    { value: '4', label: 'Level 4' },
    { value: '5', label: 'Level 5' },
    { value: '6', label: 'Level 6' },
    { value: '7', label: 'Level 7' },
    { value: '8', label: 'Level 8' },
    { value: '9', label: 'Level 9' },
];

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
        const response = await api.delete(route('units.bulk-delete'), {
            unit_ids: selectedRows.value,
        });

        if (!response.data.value?.success) {
            throw new Error(response.data.value?.message || 'Bulk delete failed');
        }

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
        window.location.href = route('units.export.excel.filtered', {
            search: filters.search || undefined,
            sort: filters.sort || undefined,
            direction: filters.direction || undefined,
            type: filters.type || undefined,
            level: filters.level || undefined,
        });

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

// Navigate to import/create với return param
const navigateToImport = () => {
    const importUrl = getLinkUrlWithReturn('units.import');
    router.visit(importUrl, { preserveScroll: true, preserveState: true });
};

const navigateToCreate = () => {
    const createUrl = getLinkUrlWithReturn('units.create');
    router.visit(createUrl, { preserveScroll: true, preserveState: true });
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
        header: 'Level',
        accessorKey: 'level',
        enableSorting: true,
        cell: ({ row }) => {
            return h('span', { class: 'text-sm' }, row.original.level);
        },
    },
    {
        header: 'Type',
        accessorKey: 'unit_type',
        enableSorting: true,
        cell: ({ row }) => {
            const typeMap: Record<string, string> = {
                general: 'General',
                egc: 'EGC',
                semi: 'Semiconductor',
                ai: 'AI',
                mkt: 'Marketing',
                ba: 'Business Admin',
                cs: 'CS',
                ee: 'EE',
                me: 'ME',
                fin: 'Finance',
            };
            return h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-800' }, typeMap[row.original.unit_type] || row.original.unit_type);
        },
    },
    {
        header: 'Base Fee',
        accessorKey: 'base_fee',
        enableSorting: true,
        cell: ({ row }) => {
            const fee = row.original.base_fee;
            if (!fee) return h('span', { class: 'text-gray-400' }, '-');

            // Format fee with K/M notation
            const formatFee = (value: number): string => {
                if (value >= 1000000) {
                    return `${(value / 1000000).toFixed(1)}M`;
                } else if (value >= 1000) {
                    return `${(value / 1000).toFixed(1)}K`;
                }
                return value.toLocaleString();
            };

            return h('span', { class: 'text-sm font-medium text-green-600' }, formatFee(fee));
        },
    },
    {
        header: 'Retake Fee',
        accessorKey: 'retake_fee',
        enableSorting: true,
        cell: ({ row }) => {
            const fee = row.original.retake_fee;
            if (!fee) return h('span', { class: 'text-gray-400' }, '-');

            // Format fee with K/M notation
            const formatFee = (value: number): string => {
                if (value >= 1000000) {
                    return `${(value / 1000000).toFixed(1)}M`;
                } else if (value >= 1000) {
                    return `${(value / 1000).toFixed(1)}K`;
                }
                return value.toLocaleString();
            };

            return h('span', { class: 'text-sm font-medium text-orange-600' }, formatFee(fee));
        },
    },
    {
        header: 'Prerequisites',
        accessorKey: 'prerequisite_conditions_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.prerequisite_conditions_count;
            return count > 0 ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800' }, count) : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'Equivalents',
        accessorKey: 'equivalent_units_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.equivalent_units_count;
            return count > 0 ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800' }, count) : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'In Curricula',
        accessorKey: 'curriculum_units_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.curriculum_units_count;
            return count > 0 ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-800' }, count) : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'Syllabus Templates',
        accessorKey: 'syllabus_templates_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.syllabus_templates_count;
            return count > 0 ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-indigo-100 text-indigo-800' }, count) : h('span', { class: 'text-gray-400' }, 'None');
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

// provided by composable
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
            <Button @click="navigateToImport" variant="outline" class="flex items-center gap-2">
                <Upload class="h-4 w-4" />
                Import Excel
            </Button>

            <Button size="sm" @click="navigateToCreate">
                <Plus class="mr-2 h-4 w-4" />
                Add Unit
            </Button>
        </div>
    </div>

    <!-- Filters Section -->
    <FilterPanel :has-active-filters="hasActiveFilters" :columns="3" @clear="clearAllFilters">
        <FilterSearchInput :model-value="filters.search" placeholder="Search units..." @update:model-value="filters.search = $event" @search="handleSearch" />
        <FilterSelect :model-value="filters.type" :options="unitTypeOptions.filter((option) => option.value !== 'all')" placeholder="All Types" all-label="All Types" @update:model-value="filters.type = $event" @change="setFilter('type', $event)" />
        <FilterSelect :model-value="filters.level" :options="levelOptions.filter((option) => option.value !== 'all')" placeholder="All Levels" all-label="All Levels" @update:model-value="filters.level = $event" @change="setFilter('level', $event)" />
    </FilterPanel>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :loading="isLoading" :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange">
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
                    Are you sure you want to delete <strong>{{ selectedRows.length }}</strong> selected units? This action cannot be undone and will permanently remove all selected units from the system.
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
