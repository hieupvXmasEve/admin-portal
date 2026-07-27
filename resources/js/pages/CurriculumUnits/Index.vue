<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { usePermissions } from '@/composables';
import { useDataTable } from '@/composables/useDataTable';
import type { PaginatedResponse } from '@/types';
import { curriculumRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Book, BookOpen, Calendar, Edit, Eye, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface CurriculumUnit {
    id: number;
    curriculum_version_id: number;
    unit_id: number;
    semester_number?: number;
    unit_scope?: string | null;
    year_level?: number | null;
    curriculum_version?: {
        id: number;
        version_code: string;
        program?: {
            name: string;
            degree_level: string;
        };
        specialization?: {
            name: string;
            code: string;
        };
    };
    unit?: {
        id: number;
        code: string;
        name: string;
        credit_points: number;
    };
    prerequisites_count: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    curriculumUnits: PaginatedResponse<CurriculumUnit>;
    filters?: {
        search?: string;
        curriculum_version_id?: string;
        unit_scope?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    curriculumVersions: Array<{
        id: number;
        version_code: string;
        program?: { name: string };
        specialization?: { name: string; code: string };
    }>;
    unitScopes: Array<{ value: string; label: string }>;
}>();

const permission = usePermissions();

// Reactive data
const data = computed(() => props.curriculumUnits.data);

interface CurriculumUnitFilters {
    search: string;
    curriculum_version_id: string;
    unit_scope: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const { filters, setFilter, clearAllFilters, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, hasActiveFilters, isLoading, currentSort, currentDirection } = useDataTable<CurriculumUnitFilters>({
    baseUrl: curriculumRoutes.curriculumUnits.index(),
    initialFilters: {
        search: props.filters?.search ?? '',
        curriculum_version_id: props.filters?.curriculum_version_id ?? '',
        unit_scope: props.filters?.unit_scope ?? '',
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,
        direction: props.filters?.direction === 'desc' ? 'desc' : props.filters?.direction === 'asc' ? 'asc' : null,
        per_page: props.filters?.per_page ?? 15,
    },
    defaultValues: { search: '', curriculum_version_id: '', unit_scope: '', sort: null, direction: null, per_page: 15 },
    only: ['curriculumUnits', 'filters'],
    fieldDebounce: { search: 400 },
    immediateFields: ['curriculum_version_id', 'unit_scope'],
});

// Computed display values for select components
const displayCurriculumVersionId = computed(() => filters.curriculum_version_id || 'all');
const displayUnitScope = computed(() => filters.unit_scope || 'all');

// Delete dialog state
const deleteDialogOpen = ref(false);
const curriculumUnitToDelete = ref<CurriculumUnit | null>(null);

// Action functions
const editCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    router.visit(route('curriculum_unit.edit', { curriculum_unit: curriculumUnit.id }));
};

const viewCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    router.visit(route('curriculum_unit.show', { curriculum_unit: curriculumUnit.id }));
};

const deleteCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    curriculumUnitToDelete.value = curriculumUnit;
    deleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (curriculumUnitToDelete.value) {
        router.delete(route('curriculum_unit.destroy', { curriculum_unit: curriculumUnitToDelete.value.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Curriculum unit deleted successfully');
                deleteDialogOpen.value = false;
                curriculumUnitToDelete.value = null;
            },
            onError: () => {
                toast.error('Failed to delete curriculum unit');
            },
        });
    }
};

const updateCurriculumVersionFilter = (value: any) => {
    setFilter('curriculum_version_id', value === 'all' ? '' : String(value || ''));
};

const updateUnitScopeFilter = (value: any) => {
    setFilter('unit_scope', value === 'all' ? '' : String(value || ''));
};

// Column definitions
const columns: ColumnDef<CurriculumUnit>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.curriculumUnits.current_page;
            const perPage = props.curriculumUnits.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Unit',
        accessorKey: 'unit.code',
        enableSorting: false,
        cell: ({ row }) => {
            const unit = row.original.unit;
            if (!unit) return h('span', { class: 'text-gray-400' }, 'No Unit');

            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'flex items-center gap-2' }, [
                    h('code', { class: 'bg-gray-100 px-2 py-1 rounded text-xs font-mono' }, unit.code),
                    row.original.unit_scope ? h(Badge, { variant: 'outline', class: 'text-xs' }, () => row.original.unit_scope) : null,
                ]),
                h('div', { class: 'font-medium text-sm' }, unit.name),
                h('div', { class: 'text-xs text-gray-500 flex items-center gap-1' }, [h(BookOpen, { class: 'h-3 w-3' }), `${unit.credit_points} credits`]),
            ]);
        },
    },
    {
        header: 'Curriculum Version',
        accessorKey: 'curriculum_version.name',
        enableSorting: false,
        cell: ({ row }) => {
            const cv = row.original.curriculum_version;
            if (!cv) return h('span', { class: 'text-gray-400' }, 'No Curriculum');

            return h(
                'div',
                { class: 'space-y-2' },
                [
                    h('div', { class: 'space-y-1' }, [h('div', { class: 'font-medium text-sm' }, cv.version_code)]),
                    cv.program ? h('div', { class: 'text-xs' }, [h('span', { class: 'font-medium' }, cv.program.name), cv.specialization ? h('span', { class: 'text-blue-600 ml-2' }, `(${cv.specialization.code})`) : null]) : null,
                ].filter(Boolean),
            );
        },
    },
    {
        header: 'Academic Details',
        accessorKey: 'year_level',
        enableSorting: false,
        cell: ({ row }) => {
            const cu = row.original;
            return h('div', { class: 'space-y-2' }, [
                h('div', { class: 'flex items-center gap-2' }, [h(Calendar, { class: 'h-3 w-3 text-gray-400' }), h('span', { class: 'text-sm font-medium' }, `Year ${cu.year_level}`)]),
                cu.semester_number ? h('div', { class: 'text-xs text-gray-600 bg-gray-50 px-2 py-1 rounded' }, `Semester ${cu.semester_number}`) : h('span', { class: 'text-xs text-gray-400' }, 'No semester'),
            ]);
        },
    },
    {
        header: 'Prerequisites',
        accessorKey: 'prerequisites_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.prerequisites_count;
            return count > 0
                ? h('div', { class: 'flex items-center gap-1' }, [h(Book, { class: 'h-3 w-3 text-orange-500' }), h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-orange-100 text-orange-800' }, count)])
                : h('span', { class: 'text-gray-400 text-xs' }, 'None');
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
</script>

<template>
    <Head title="Curriculum Units" />
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Curriculum Units</h1>
        <div class="flex items-center gap-2">
            <Button v-if="permission.can('create_curriculum_unit')" size="sm" @click="router.visit(curriculumRoutes.curriculumUnits.create())">
                <Plus class="mr-2 h-4 w-4" />
                Add Curriculum Unit
            </Button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <div class="relative">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <DebouncedInput v-model="filters.search" @debounced="handleSearch" placeholder="Search curriculum units..." class="pl-9" :debounce="400" />
            </div>
        </div>

        <div class="min-w-[180px]">
            <Select :model-value="displayCurriculumVersionId" @update:model-value="updateCurriculumVersionFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All curricula" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All curricula</SelectItem>
                    <SelectItem v-for="curriculum in curriculumVersions" :key="curriculum.id" :value="curriculum.id.toString()"> {{ curriculum.version_code }} </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="min-w-[180px]">
            <Select :model-value="displayUnitScope" @update:model-value="updateUnitScopeFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All unit scopes" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All unit scopes</SelectItem>
                    <SelectItem v-for="scope in unitScopes" :key="scope.value" :value="scope.value">{{ scope.label }}</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearAllFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <!-- Data Table -->
    <div class="rounded-md border">
        <DataTable :data="data" :columns="columns" :loading="isLoading" :initial-sort="currentSort ?? undefined" :initial-direction="currentDirection ?? undefined" @sort-change="handleSortChange">
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="viewCurriculumUnit(row.original)" title="View curriculum unit">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View curriculum unit</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <TooltipProvider v-if="permission.can('edit_curriculum_unit')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="editCurriculumUnit(row.original)" title="Edit curriculum unit">
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit curriculum unit</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <TooltipProvider v-if="permission.can('delete_curriculum_unit')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="deleteCurriculumUnit(row.original)" title="Delete curriculum unit">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Delete curriculum unit</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>
    </div>

    <!-- Pagination -->
    <DataPagination :pagination-data="curriculumUnits" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Curriculum Unit</AlertDialogTitle>
                <AlertDialogDescription> Are you sure you want to delete this curriculum unit assignment? This action cannot be undone and will remove the unit from the curriculum version. </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="deleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" class="bg-red-600 hover:bg-red-700">Delete Curriculum Unit</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
