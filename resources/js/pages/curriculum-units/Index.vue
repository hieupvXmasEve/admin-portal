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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { Book, BookOpen, Calendar, Edit, Eye, FileSpreadsheet, Plus, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface CurriculumUnit {
    id: number;
    curriculum_version_id: number;
    unit_id: number;
    semester_id?: number;
    is_core: boolean;
    year_level: number;
    curriculum_version?: {
        id: number;
        name: string;
        version: string;
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
    semester?: {
        id: number;
        name: string;
        number: number;
    };
    prerequisites_count: number;
    created_at: string;
    updated_at: string;
}

interface Statistics {
    total_curriculum_units: number;
    core_units: number;
    elective_units: number;
    total_credit_points: number;
    by_year_level: Record<string, number>;
    by_semester: Record<string, number>;
    avg_units_per_curriculum: number;
}

const props = defineProps<{
    curriculumUnits: PaginatedResponse<CurriculumUnit>;
    filters?: {
        search?: string;
        curriculum_version_id?: string;
        semester_id?: string;
        year_level?: string;
        is_core?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    statistics: Statistics;
    curriculumVersions: Array<{
        id: number;
        name: string;
        version: string;
        program?: { name: string };
        specialization?: { name: string; code: string };
    }>;
    semesters: Array<{ id: number; name: string; number: number }>;
    yearLevels: number[];
}>();

const page = usePage();

// Reactive data
const data = computed(() => props.curriculumUnits.data);

// Filter state
const filters = ref({
    search: props.filters?.search || '',
    curriculum_version_id: props.filters?.curriculum_version_id || '',
    semester_id: props.filters?.semester_id || '',
    year_level: props.filters?.year_level || '',
    is_core: props.filters?.is_core || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

// Computed display values for select components
const displayCurriculumVersionId = computed(() => filters.value.curriculum_version_id || 'all');
const displaySemesterId = computed(() => filters.value.semester_id || 'all');
const displayYearLevel = computed(() => filters.value.year_level || 'all');
const displayIsCore = computed(() => filters.value.is_core || 'all');

// Selected rows for bulk actions
const selectedRows = ref<number[]>([]);

// Delete dialog state
const deleteDialogOpen = ref(false);
const curriculumUnitToDelete = ref<CurriculumUnit | null>(null);

// Bulk delete dialog state
const bulkDeleteDialogOpen = ref(false);

// Permission check function
const can = (permission: string) => {
    const permissions = (page.props as any).permissions || [];
    return permissions.includes(permission);
};

// Action functions
const editCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    router.visit(`/curriculum-units/edit/${curriculumUnit.id}`);
};

const viewCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    router.visit(`/curriculum-units/${curriculumUnit.id}`);
};

const deleteCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    curriculumUnitToDelete.value = curriculumUnit;
    deleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (curriculumUnitToDelete.value) {
        router.delete(`/curriculum-units/${curriculumUnitToDelete.value.id}`, {
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

// Toggle core/elective status
const toggleCoreStatus = (curriculumUnit: CurriculumUnit) => {
    router.patch(
        `/curriculum-units/${curriculumUnit.id}/toggle-core`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Unit marked as ${curriculumUnit.is_core ? 'elective' : 'core'} successfully`);
            },
            onError: () => {
                toast.error('Failed to update unit status');
            },
        },
    );
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.curriculum_version_id) params.set('curriculum_version_id', newFilters.curriculum_version_id);
    if (newFilters.semester_id) params.set('semester_id', newFilters.semester_id);
    if (newFilters.year_level) params.set('year_level', newFilters.year_level);
    if (newFilters.is_core) params.set('is_core', newFilters.is_core);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/curriculum-units${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['curriculumUnits', 'filters'],
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

const updateCurriculumVersionFilter = (value: any) => {
    filters.value.curriculum_version_id = value === 'all' ? '' : String(value || '');
    applyFilters(filters.value);
};

const updateSemesterFilter = (value: any) => {
    filters.value.semester_id = value === 'all' ? '' : String(value || '');
    applyFilters(filters.value);
};

const updateYearLevelFilter = (value: any) => {
    filters.value.year_level = value === 'all' ? '' : String(value || '');
    applyFilters(filters.value);
};

const updateCoreFilter = (value: any) => {
    filters.value.is_core = value === 'all' ? '' : String(value || '');
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        curriculum_version_id: '',
        semester_id: '',
        year_level: '',
        is_core: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    router.visit('/curriculum-units', {
        preserveState: true,
        preserveScroll: true,
        only: ['curriculumUnits', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return (
        filters.value.search || filters.value.curriculum_version_id || filters.value.semester_id || filters.value.year_level || filters.value.is_core
    );
});

// Bulk delete functionality
const isBulkDeleting = ref(false);

const confirmBulkDelete = async () => {
    if (selectedRows.value.length === 0) return;

    isBulkDeleting.value = true;

    try {
        await fetch('/api/curriculum-units/bulk-delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                curriculum_unit_ids: selectedRows.value,
            }),
        });

        selectedRows.value = [];
        bulkDeleteDialogOpen.value = false;
        toast.success('Curriculum units deleted successfully');
        router.reload();
    } catch (error) {
        console.error('Bulk delete failed:', error);
        toast.error('Failed to delete curriculum units');
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
        if (filters.value.curriculum_version_id) params.set('curriculum_version_id', filters.value.curriculum_version_id);
        if (filters.value.semester_id) params.set('semester_id', filters.value.semester_id);
        if (filters.value.year_level) params.set('year_level', filters.value.year_level);
        if (filters.value.is_core) params.set('is_core', filters.value.is_core);

        const exportUrl = `/curriculum-units/export/excel/filtered${params.toString() ? '?' + params.toString() : ''}`;

        window.location.href = exportUrl;

        setTimeout(() => {
            toast.success('Export started successfully');
        }, 500);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export curriculum units');
    } finally {
        isExporting.value = false;
    }
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
        enableSorting: true,
        cell: ({ row }) => {
            const unit = row.original.unit;
            if (!unit) return h('span', { class: 'text-gray-400' }, 'No Unit');

            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'flex items-center gap-2' }, [
                    h('code', { class: 'bg-gray-100 px-2 py-1 rounded text-xs font-mono' }, unit.code),
                    h(
                        Badge,
                        {
                            variant: row.original.is_core ? 'default' : 'secondary',
                            class: 'text-xs',
                        },
                        row.original.is_core ? 'Core' : 'Elective',
                    ),
                ]),
                h('div', { class: 'font-medium text-sm' }, unit.name),
                h('div', { class: 'text-xs text-gray-500 flex items-center gap-1' }, [
                    h(BookOpen, { class: 'h-3 w-3' }),
                    `${unit.credit_points} credits`,
                ]),
            ]);
        },
    },
    {
        header: 'Curriculum Version',
        accessorKey: 'curriculum_version.name',
        enableSorting: true,
        cell: ({ row }) => {
            const cv = row.original.curriculum_version;
            if (!cv) return h('span', { class: 'text-gray-400' }, 'No Curriculum');

            return h(
                'div',
                { class: 'space-y-2' },
                [
                    h('div', { class: 'space-y-1' }, [
                        h('div', { class: 'font-medium text-sm' }, cv.name),
                        h('div', { class: 'text-xs text-gray-500' }, `Version ${cv.version}`),
                    ]),
                    cv.program
                        ? h('div', { class: 'text-xs' }, [
                              h('span', { class: 'font-medium' }, cv.program.name),
                              cv.specialization ? h('span', { class: 'text-blue-600 ml-2' }, `(${cv.specialization.code})`) : null,
                          ])
                        : null,
                ].filter(Boolean),
            );
        },
    },
    {
        header: 'Academic Details',
        accessorKey: 'year_level',
        enableSorting: true,
        cell: ({ row }) => {
            const cu = row.original;
            return h('div', { class: 'space-y-2' }, [
                h('div', { class: 'flex items-center gap-2' }, [
                    h(Calendar, { class: 'h-3 w-3 text-gray-400' }),
                    h('span', { class: 'text-sm font-medium' }, `Year ${cu.year_level}`),
                ]),
                cu.semester
                    ? h('div', { class: 'text-xs text-gray-600 bg-gray-50 px-2 py-1 rounded' }, cu.semester.name)
                    : h('span', { class: 'text-xs text-gray-400' }, 'No semester'),
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
                ? h('div', { class: 'flex items-center gap-1' }, [
                      h(Book, { class: 'h-3 w-3 text-orange-500' }),
                      h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-orange-100 text-orange-800' }, count),
                  ])
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

// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['curriculumUnits'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
</script>

<template>
    <Head title="Curriculum Units" />
    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Units</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.total_curriculum_units }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Core Units</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-blue-600">{{ statistics.core_units }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Elective Units</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-green-600">{{ statistics.elective_units }}</div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Credit Points</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.total_credit_points }}</div>
            </CardContent>
        </Card>
    </div>

    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Curriculum Units</h1>
        <div class="flex items-center gap-2">
            <Button @click="exportToExcel" variant="outline" :disabled="isExporting" class="flex items-center gap-2">
                <FileSpreadsheet class="h-4 w-4" />
                {{ isExporting ? 'Exporting...' : 'Export Excel' }}
            </Button>
            <Button @click="router.visit('/curriculum-units/import')" variant="outline" class="flex items-center gap-2">
                <Upload class="h-4 w-4" />
                Import Excel
            </Button>

            <Button v-if="can('create_curriculum_unit')" size="sm" @click="router.visit('/curriculum-units/create')">
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
                <Input placeholder="Search curriculum units..." :model-value="filters.search" @update:model-value="updateSearchFilter" class="pl-9" />
            </div>
        </div>

        <div class="min-w-[180px]">
            <Select :model-value="displayCurriculumVersionId" @update:model-value="updateCurriculumVersionFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All curricula" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All curricula</SelectItem>
                    <SelectItem v-for="curriculum in curriculumVersions" :key="curriculum.id" :value="curriculum.id.toString()">
                        {{ curriculum.name }} ({{ curriculum.version }})
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="min-w-[150px]">
            <Select :model-value="displaySemesterId" @update:model-value="updateSemesterFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All semesters" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All semesters</SelectItem>
                    <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                        {{ semester.name }} ({{ semester.number }})
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="min-w-[120px]">
            <Select :model-value="displayYearLevel" @update:model-value="updateYearLevelFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All years" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All years</SelectItem>
                    <SelectItem v-for="year in yearLevels" :key="year" :value="year.toString()">
                        {{ year }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="min-w-[120px]">
            <Select :model-value="displayIsCore" @update:model-value="updateCoreFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All types" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All types</SelectItem>
                    <SelectItem value="1">Core units</SelectItem>
                    <SelectItem value="0">Elective units</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <!-- Data Table -->
    <div class="rounded-md border">
        <DataTable :data="data" :columns="columns" :loading="false">
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

                    <TooltipProvider v-if="can('edit_curriculum_unit')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
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

                    <TooltipProvider v-if="can('manage_curriculum_unit')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="toggleCoreStatus(row.original)"
                                    :title="row.original.is_core ? 'Mark as Elective' : 'Mark as Core'"
                                    :class="row.original.is_core ? 'text-blue-600 hover:text-blue-700' : 'text-green-600 hover:text-green-700'"
                                >
                                    <Badge :variant="row.original.is_core ? 'default' : 'secondary'" class="text-xs">
                                        {{ row.original.is_core ? 'Core' : 'Elective' }}
                                    </Badge>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{{ row.original.is_core ? 'Mark as Elective' : 'Mark as Core' }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <TooltipProvider v-if="can('delete_curriculum_unit')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
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
                <AlertDialogDescription>
                    Are you sure you want to delete this curriculum unit assignment? This action cannot be undone and will remove the unit from the
                    curriculum version.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="deleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" class="bg-red-600 hover:bg-red-700">Delete Curriculum Unit</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Bulk Delete Confirmation Dialog -->
    <AlertDialog :open="bulkDeleteDialogOpen" @update:open="bulkDeleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Multiple Curriculum Units</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete <strong>{{ selectedRows.length }}</strong> selected curriculum units? This action cannot be undone
                    and will remove all selected units from their curriculum versions.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="bulkDeleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmBulkDelete" :disabled="isBulkDeleting" class="bg-red-600 hover:bg-red-700">
                    {{ isBulkDeleting ? 'Deleting...' : 'Delete Curriculum Units' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
