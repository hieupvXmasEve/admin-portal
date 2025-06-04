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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { Book, Calendar, Edit, Eye, FileSpreadsheet, Plus, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface CurriculumVersion {
    id: number;
    name: string;
    version: string;
    year: number;
    is_active: boolean;
    program_id: number;
    specialization_id?: number;
    program?: {
        id: number;
        name: string;
        degree_level: string;
    };
    specialization?: {
        id: number;
        name: string;
        code: string;
    };
    curriculum_units_count: number;
    total_credit_points: number;
    created_at: string;
    updated_at: string;
}

interface Statistics {
    total_curriculum_versions: number;
    active_versions: number;
    inactive_versions: number;
    avg_credit_points: number;
    by_year: Record<string, number>;
    by_program: Record<string, number>;
}

const props = defineProps<{
    curriculumVersions: PaginatedResponse<CurriculumVersion>;
    filters?: {
        search?: string;
        program_id?: string;
        specialization_id?: string;
        year?: string;
        is_active?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    statistics: Statistics;
    programs: Array<{ id: number; name: string; degree_level: string }>;
    specializations: Array<{ id: number; name: string; code: string; program_id: number }>;
    years: number[];
}>();

const page = usePage();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Curriculum Versions',
        href: '/curriculum-versions',
    },
];

// Reactive data
const data = computed(() => props.curriculumVersions.data);

// Filter state
const filters = ref({
    search: props.filters?.search || '',
    program_id: props.filters?.program_id || '',
    specialization_id: props.filters?.specialization_id || '',
    year: props.filters?.year || '',
    is_active: props.filters?.is_active || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

// Computed specializations based on selected program
const filteredSpecializations = computed(() => {
    if (!filters.value.program_id) return props.specializations;
    return props.specializations.filter((spec) => spec.program_id.toString() === filters.value.program_id);
});

// Selected rows for bulk actions
const selectedRows = ref<number[]>([]);

// Delete dialog state
const deleteDialogOpen = ref(false);
const curriculumVersionToDelete = ref<CurriculumVersion | null>(null);

// Bulk delete dialog state
const bulkDeleteDialogOpen = ref(false);

// Permission check function
const can = (permission: string) => {
    const permissions = (page.props as any).permissions || [];
    return permissions.includes(permission);
};

// Action functions
const editCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    router.visit(`/curriculum-versions/edit/${curriculumVersion.id}`);
};

const viewCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    router.visit(`/curriculum-versions/${curriculumVersion.id}`);
};

const deleteCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    curriculumVersionToDelete.value = curriculumVersion;
    deleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (curriculumVersionToDelete.value) {
        router.delete(`/curriculum-versions/${curriculumVersionToDelete.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Curriculum version deleted successfully');
                deleteDialogOpen.value = false;
                curriculumVersionToDelete.value = null;
            },
            onError: () => {
                toast.error('Failed to delete curriculum version');
            },
        });
    }
};

// Toggle active status
const toggleActiveStatus = (curriculumVersion: CurriculumVersion) => {
    router.patch(
        `/curriculum-versions/${curriculumVersion.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Curriculum version ${curriculumVersion.is_active ? 'deactivated' : 'activated'} successfully`);
            },
            onError: () => {
                toast.error('Failed to update curriculum version status');
            },
        },
    );
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.program_id) params.set('program_id', newFilters.program_id);
    if (newFilters.specialization_id) params.set('specialization_id', newFilters.specialization_id);
    if (newFilters.year) params.set('year', newFilters.year);
    if (newFilters.is_active) params.set('is_active', newFilters.is_active);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/curriculum-versions${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['curriculumVersions', 'filters'],
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
    filters.value.program_id = String(value || '');
    filters.value.specialization_id = ''; // Reset specialization when program changes
    applyFilters(filters.value);
};

const updateSpecializationFilter = (value: any) => {
    filters.value.specialization_id = String(value || '');
    applyFilters(filters.value);
};

const updateYearFilter = (value: any) => {
    filters.value.year = String(value || '');
    applyFilters(filters.value);
};

const updateActiveFilter = (value: any) => {
    filters.value.is_active = String(value || '');
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        program_id: '',
        specialization_id: '',
        year: '',
        is_active: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    router.visit('/curriculum-versions', {
        preserveState: true,
        preserveScroll: true,
        only: ['curriculumVersions', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || filters.value.program_id || filters.value.specialization_id || filters.value.year || filters.value.is_active;
});

// Bulk delete functionality
const isBulkDeleting = ref(false);

const confirmBulkDelete = async () => {
    if (selectedRows.value.length === 0) return;

    isBulkDeleting.value = true;

    try {
        await fetch('/api/curriculum-versions/bulk-delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                curriculum_version_ids: selectedRows.value,
            }),
        });

        selectedRows.value = [];
        bulkDeleteDialogOpen.value = false;
        toast.success('Curriculum versions deleted successfully');
        router.reload();
    } catch (error) {
        console.error('Bulk delete failed:', error);
        toast.error('Failed to delete curriculum versions');
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
        if (filters.value.specialization_id) params.set('specialization_id', filters.value.specialization_id);
        if (filters.value.year) params.set('year', filters.value.year);
        if (filters.value.is_active) params.set('is_active', filters.value.is_active);

        const exportUrl = `/curriculum-versions/export/excel/filtered${params.toString() ? '?' + params.toString() : ''}`;

        window.location.href = exportUrl;

        setTimeout(() => {
            toast.success('Export started successfully');
        }, 500);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export curriculum versions');
    } finally {
        isExporting.value = false;
    }
};

// Column definitions
const columns: ColumnDef<CurriculumVersion>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.curriculumVersions.current_page;
            const perPage = props.curriculumVersions.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => {
            const cv = row.original;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'font-medium text-sm' }, cv.name),
                h('div', { class: 'text-xs text-gray-500' }, `Version ${cv.version}`),
            ]);
        },
    },
    {
        header: 'Program & Specialization',
        accessorKey: 'program.name',
        enableSorting: true,
        cell: ({ row }) => {
            const cv = row.original;
            const program = cv.program;
            const specialization = cv.specialization;

            return h(
                'div',
                { class: 'space-y-2' },
                [
                    program
                        ? h('div', { class: 'space-y-1' }, [
                              h('div', { class: 'font-medium text-sm' }, program.name),
                              h(
                                  Badge,
                                  {
                                      variant:
                                          program.degree_level === 'bachelor'
                                              ? 'default'
                                              : program.degree_level === 'master'
                                                ? 'secondary'
                                                : 'outline',
                                      class: 'capitalize text-xs',
                                  },
                                  program.degree_level,
                              ),
                          ])
                        : null,
                    specialization
                        ? h('div', { class: 'text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded' }, [
                              h('span', { class: 'font-mono' }, specialization.code),
                              ' - ',
                              specialization.name,
                          ])
                        : null,
                ].filter(Boolean),
            );
        },
    },
    {
        header: 'Year',
        accessorKey: 'year',
        enableSorting: true,
        cell: ({ row }) => {
            return h('div', { class: 'flex items-center gap-2' }, [
                h(Calendar, { class: 'h-4 w-4 text-gray-400' }),
                h('span', { class: 'font-medium' }, row.original.year),
            ]);
        },
    },
    {
        header: 'Status',
        accessorKey: 'is_active',
        enableSorting: true,
        cell: ({ row }) => {
            const isActive = row.original.is_active;
            return h(
                Badge,
                {
                    variant: isActive ? 'default' : 'secondary',
                    class: 'capitalize',
                },
                isActive ? 'Active' : 'Inactive',
            );
        },
    },
    {
        header: 'Units & Credits',
        accessorKey: 'curriculum_units_count',
        enableSorting: false,
        cell: ({ row }) => {
            const cv = row.original;
            return h('div', { class: 'space-y-1 text-sm' }, [
                h('div', { class: 'flex items-center gap-1' }, [
                    h(Book, { class: 'h-3 w-3 text-gray-400' }),
                    h('span', {}, `${cv.curriculum_units_count} units`),
                ]),
                h('div', { class: 'text-xs text-gray-500' }, `${cv.total_credit_points} credits`),
            ]);
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
        only: ['curriculumVersions'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
</script>

<template>
    <Head title="Curriculum Versions" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Versions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ statistics.total_curriculum_versions }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Versions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ statistics.active_versions }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Inactive Versions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-500">{{ statistics.inactive_versions }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Avg. Credit Points</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ statistics.avg_credit_points }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Header with Add Button -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Curriculum Versions</h1>
                <div class="flex items-center gap-2">
                    <Button @click="exportToExcel" variant="outline" :disabled="isExporting" class="flex items-center gap-2">
                        <FileSpreadsheet class="h-4 w-4" />
                        {{ isExporting ? 'Exporting...' : 'Export Excel' }}
                    </Button>
                    <Button @click="router.visit('/curriculum-versions/import')" variant="outline" class="flex items-center gap-2">
                        <Upload class="h-4 w-4" />
                        Import Excel
                    </Button>

                    <Button v-if="can('create_curriculum_version')" size="sm" @click="router.visit('/curriculum-versions/create')">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Curriculum Version
                    </Button>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
                <div class="min-w-[200px] flex-1">
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            placeholder="Search curriculum versions..."
                            :model-value="filters.search"
                            @update:model-value="updateSearchFilter"
                            class="pl-9"
                        />
                    </div>
                </div>

                <div class="min-w-[150px]">
                    <Select :model-value="filters.program_id" @update:model-value="updateProgramFilter">
                        <SelectTrigger>
                            <SelectValue placeholder="All programs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All programs</SelectItem>
                            <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                {{ program.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[150px]">
                    <Select :model-value="filters.specialization_id" @update:model-value="updateSpecializationFilter" :disabled="!filters.program_id">
                        <SelectTrigger>
                            <SelectValue placeholder="All specializations" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All specializations</SelectItem>
                            <SelectItem
                                v-for="specialization in filteredSpecializations"
                                :key="specialization.id"
                                :value="specialization.id.toString()"
                            >
                                {{ specialization.code }} - {{ specialization.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[120px]">
                    <Select :model-value="filters.year" @update:model-value="updateYearFilter">
                        <SelectTrigger>
                            <SelectValue placeholder="All years" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All years</SelectItem>
                            <SelectItem v-for="year in years" :key="year" :value="year.toString()">
                                {{ year }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[120px]">
                    <Select :model-value="filters.is_active" @update:model-value="updateActiveFilter">
                        <SelectTrigger>
                            <SelectValue placeholder="All status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All status</SelectItem>
                            <SelectItem value="1">Active</SelectItem>
                            <SelectItem value="0">Inactive</SelectItem>
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
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="viewCurriculumVersion(row.original)"
                                            title="View curriculum version"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>View curriculum version</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>

                            <TooltipProvider
                                v-if="can('edit_curriculum_version')"
                                :delay-duration="0"
                                ignore-non-keyboard-focus
                                disable-hoverable-content
                            >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="editCurriculumVersion(row.original)"
                                            title="Edit curriculum version"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Edit curriculum version</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>

                            <TooltipProvider
                                v-if="can('manage_curriculum_version')"
                                :delay-duration="0"
                                ignore-non-keyboard-focus
                                disable-hoverable-content
                            >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleActiveStatus(row.original)"
                                            :title="row.original.is_active ? 'Deactivate' : 'Activate'"
                                            :class="
                                                row.original.is_active ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700'
                                            "
                                        >
                                            <Badge variant="outline" :class="row.original.is_active ? 'border-red-200' : 'border-green-200'">
                                                {{ row.original.is_active ? 'Active' : 'Inactive' }}
                                            </Badge>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{{ row.original.is_active ? 'Deactivate' : 'Activate' }} curriculum version</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>

                            <TooltipProvider
                                v-if="can('delete_curriculum_version')"
                                :delay-duration="0"
                                ignore-non-keyboard-focus
                                disable-hoverable-content
                            >
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="deleteCurriculumVersion(row.original)"
                                            title="Delete curriculum version"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Delete curriculum version</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </template>
                </DataTable>
            </div>

            <!-- Pagination -->
            <DataPagination :pagination-data="curriculumVersions" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Curriculum Version</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete curriculum version <strong>{{ curriculumVersionToDelete?.name }}</strong
                        >? This action cannot be undone and will permanently remove the curriculum version and all its associated units.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="deleteDialogOpen = false">Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete" class="bg-red-600 hover:bg-red-700">Delete Curriculum Version</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Bulk Delete Confirmation Dialog -->
        <AlertDialog :open="bulkDeleteDialogOpen" @update:open="bulkDeleteDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Multiple Curriculum Versions</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete <strong>{{ selectedRows.length }}</strong> selected curriculum versions? This action cannot be
                        undone and will permanently remove all selected curriculum versions and their associated units.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="bulkDeleteDialogOpen = false">Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="confirmBulkDelete" :disabled="isBulkDeleting" class="bg-red-600 hover:bg-red-700">
                        {{ isBulkDeleting ? 'Deleting...' : 'Delete Curriculum Versions' }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
