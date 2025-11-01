<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { usePermissions } from '@/composables';
import type { PaginatedResponse } from '@/types';
import { ValidationRules } from '@/types/validation';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { useDebounceFn } from '@vueuse/core';
import { Book, Copy, Edit, Eye, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface CurriculumVersion {
    id: number;
    program_id: number;
    specialization_id?: number;
    version_code: string;
    semester_id: number;
    notes?: string;
    program?: {
        id: number;
        name: string;
        code: string;
    };
    specialization?: {
        id: number;
        name: string;
        code: string;
    };
    effective_from_semester?: {
        id: number;
        name: string;
        code: string;
    };
    curriculum_units_count: number;
    created_at: string;
    updated_at: string;
}

interface Statistics {
    total_curriculum_versions: number;
    active_versions: number;
    inactive_versions: number;
    by_year: Record<string, number>;
    by_program: Record<string, number>;
}

const props = defineProps<{
    curriculumVersions: PaginatedResponse<CurriculumVersion>;
    filters?: {
        search?: string;
        program_id?: string;
        specialization_id?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    statistics: Statistics;
    programs: Array<{ id: number; name: string; code: string }>;
    specializations: Array<{ id: number; name: string; code: string; program_id: number }>;
    semesters?: Array<{ id: number; name: string; code: string }>;
}>();

// Reactive data
const data = computed(() => props.curriculumVersions.data);

// Filter state - Handle case where props.filters might be empty array or object
const getInitialFilters = () => {
    // Check if props.filters is a valid object (not array) and has properties
    const validFilters = props.filters && typeof props.filters === 'object' && !Array.isArray(props.filters) ? props.filters : {};

    return {
        search: validFilters.search || '',
        program_id: validFilters.program_id || '',
        specialization_id: validFilters.specialization_id || '',
        sort: validFilters.sort || '',
        direction: validFilters.direction || 'asc',
        per_page: validFilters.per_page || 15,
    };
};

const filtersState = ref(getInitialFilters());

// Track if user is currently interacting with filters to prevent overriding their input
const isUserInteracting = ref(false);

// Debounced function to reset user interaction flag
const resetUserInteraction = useDebounceFn(() => {
    isUserInteracting.value = false;
}, 800);

// Sync filters with props when they change (e.g., from navigation), but only if user is not currently typing
watch(
    () => props.filters,
    (newFilters, oldFilters) => {
        // Only sync if user is not currently interacting and filters actually changed
        if (!isUserInteracting.value && newFilters && typeof newFilters === 'object' && !Array.isArray(newFilters) && JSON.stringify(newFilters) !== JSON.stringify(oldFilters)) {
            const updatedFilters = {
                search: newFilters.search || '',
                program_id: newFilters.program_id || '',
                specialization_id: newFilters.specialization_id || '',
                sort: newFilters.sort || '',
                direction: newFilters.direction || 'asc',
                per_page: newFilters.per_page || 15,
            };

            // Only update if there's actually a difference to prevent unnecessary reactivity
            if (JSON.stringify(filtersState.value) !== JSON.stringify(updatedFilters)) {
                filtersState.value = updatedFilters;
            }
        }
    },
    { immediate: false }, // Don't run on initial mount
);

// Ensure filters are properly initialized on mount
onMounted(async () => {
    await nextTick();

    // Debug: Log initial filter state
    console.log('Initial filters:', filtersState.value);
    console.log('Props filters:', props.filters);

    // On initial mount, sync once from props if available
    if (props.filters && Object.values(props.filters).some((value) => value !== undefined && value !== '' && value !== null)) {
        const initialFilters = {
            search: props.filters.search || '',
            program_id: props.filters.program_id || '',
            specialization_id: props.filters.specialization_id || '',
            sort: props.filters.sort || '',
            direction: props.filters.direction || 'asc',
            per_page: props.filters.per_page || 15,
        };

        if (JSON.stringify(filtersState.value) !== JSON.stringify(initialFilters)) {
            filtersState.value = initialFilters;
        }
        console.log('Filters synced from server-side props');
    } else {
        console.log('No initial filters, starting with defaults');
    }
});

// Computed specializations based on selected program
const filteredSpecializations = computed(() => {
    if (!filtersState.value.program_id) return props.specializations;
    return props.specializations.filter((spec) => spec.program_id.toString() === filtersState.value.program_id);
});

// Watch for program changes to reset specialization
watch(
    () => filtersState.value.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId && filtersState.value.specialization_id) {
            // Check if current specialization belongs to new program
            const currentSpec = props.specializations.find((spec) => spec.id.toString() === filtersState.value.specialization_id);
            if (!currentSpec || currentSpec.program_id.toString() !== newProgramId) {
                filtersState.value.specialization_id = '';
            }
        }
    },
);

// Computed display values for select components
const displayProgramId = computed(() => filtersState.value.program_id || 'all');
const displaySpecializationId = computed(() => filtersState.value.specialization_id || 'all');

// Computed for checking if filters have active values
const hasActiveFilters = computed(() => {
    return !!(filtersState.value.search || filtersState.value.program_id || filtersState.value.specialization_id);
});

// Delete dialog state
const deleteDialogOpen = ref(false);
const curriculumVersionToDelete = ref<CurriculumVersion | null>(null);

// Edit modal state
const showEditModal = ref(false);
const curriculumVersionToEdit = ref<CurriculumVersion | null>(null);

// Duplicate modal state
const showDuplicateModal = ref(false);
const curriculumVersionToDuplicate = ref<CurriculumVersion | null>(null);

// Define validation schema for edit form
const editFormSchema = toTypedSchema(
    z.object({
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().optional(),
        version_code: z.string().min(ValidationRules.curriculumVersion.versionCode.minLength, 'Version code is required').max(ValidationRules.curriculumVersion.versionCode.maxLength, 'Version code cannot exceed 50 characters'),
        semester_id: z.string().min(1, 'Semester is required'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
    }),
);

// Define validation schema for duplicate form
const duplicateFormSchema = toTypedSchema(
    z.object({
        version_code: z.string().min(1, 'Version code is required').max(20, 'Version code cannot exceed 20 characters'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
        include_curriculum_units: z.boolean(),
    }),
);

// Form setup for edit modal
const { isSubmitting } = useForm({
    validationSchema: editFormSchema,
});

// Computed specializations filtered by selected program for edit modal
const editFilteredSpecializations = computed(() => {
    if (!curriculumVersionToEdit.value?.program_id || !props.specializations) return props.specializations || [];
    return props.specializations.filter((spec) => spec.program_id === curriculumVersionToEdit.value!.program_id);
});

const permission = usePermissions();

// Action functions
const editCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    curriculumVersionToEdit.value = curriculumVersion;
    showEditModal.value = true;
};

const viewCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    router.visit(`/curriculum-versions/${curriculumVersion.id}/overview`);
};

const deleteCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    curriculumVersionToDelete.value = curriculumVersion;
    deleteDialogOpen.value = true;
};

const duplicateCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    curriculumVersionToDuplicate.value = curriculumVersion;
    showDuplicateModal.value = true;
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

// Edit modal functions
const closeEditModal = () => {
    showEditModal.value = false;
    curriculumVersionToEdit.value = null;
};

const onEditSubmit = (values: any) => {
    if (!curriculumVersionToEdit.value) return;

    const submitData = {
        ...values,
        program_id: parseInt(values.program_id),
        specialization_id: values.specialization_id ? parseInt(values.specialization_id) : null,
        semester_id: values.semester_id ? parseInt(values.semester_id) : null,
    };

    router.put(`/curriculum-versions/${curriculumVersionToEdit.value.id}`, submitData, {
        onSuccess: () => {
            toast.success('Curriculum version updated successfully');
            closeEditModal();
        },
        onError: () => {
            toast.error('Failed to update curriculum version');
        },
    });
};

// Duplicate modal functions
const closeDuplicateModal = () => {
    showDuplicateModal.value = false;
    curriculumVersionToDuplicate.value = null;
};

const onDuplicateSubmit = (values: any) => {
    if (!curriculumVersionToDuplicate.value) return;

    const submitData = {
        version_code: values.version_code,
        notes: values.notes || null,
        include_curriculum_units: values.include_curriculum_units,
    };

    router.post(`/curriculum-versions/${curriculumVersionToDuplicate.value.id}/duplicate`, submitData, {
        onSuccess: () => {
            toast.success(`Curriculum version duplicated successfully as '${values.version_code}'`);
            closeDuplicateModal();
        },
        onError: () => {
            toast.error('Failed to duplicate curriculum version');
        },
    });
};

// Generate suggested version code for duplicate
const generateSuggestedVersionCode = (originalVersionCode: string): string => {
    const year = new Date().getFullYear();
    const timestamp = Date.now().toString().slice(-4);
    return `${originalVersionCode}-Copy-${timestamp}`;
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filtersState.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.program_id) params.set('program_id', newFilters.program_id);
    if (newFilters.specialization_id) params.set('specialization_id', newFilters.specialization_id);
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

// Filter application functions

// Handle search using DebouncedInput pattern (following development standards)
const handleSearch = (value: string | number) => {
    isUserInteracting.value = true;
    filtersState.value.search = String(value);

    // Reset the flag and apply filters
    resetUserInteraction();
    router.get('/curriculum-versions', filtersState.value, {
        preserveState: true,
        preserveScroll: true,
        only: ['curriculumVersions', 'filters'],
    });
};

const updateProgramFilter = (value: any) => {
    isUserInteracting.value = true;
    filtersState.value.program_id = value === 'all' ? '' : String(value || '');

    // Auto-reset specialization when program changes
    if (!filtersState.value.program_id || !filteredSpecializations.value.some((spec) => spec.id.toString() === filtersState.value.specialization_id)) {
        filtersState.value.specialization_id = '';
    }

    // Reset the flag after applying filters
    resetUserInteraction();

    applyFilters(filtersState.value);
};

const updateSpecializationFilter = (value: any) => {
    isUserInteracting.value = true;
    filtersState.value.specialization_id = value === 'all' ? '' : String(value || '');

    // Reset the flag after applying filters
    resetUserInteraction();

    applyFilters(filtersState.value);
};

const clearFilters = () => {
    isUserInteracting.value = true;
    filtersState.value = {
        search: '',
        program_id: '',
        specialization_id: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };

    // Reset the flag after navigation
    resetUserInteraction();

    router.visit('/curriculum-versions', {
        preserveState: true,
        preserveScroll: true,
        only: ['curriculumVersions', 'filters'],
    });
};

// Export functionality
// const isExporting = ref(false);

// const exportToExcel = async () => {
//     if (isExporting.value) return;

//     isExporting.value = true;

//     try {
//         const params = new URLSearchParams();

//         if (filtersState.value.search) params.set('search', filtersState.value.search);
//         if (filtersState.value.program_id) params.set('program_id', filtersState.value.program_id);
//         if (filtersState.value.specialization_id) params.set('specialization_id', filtersState.value.specialization_id);

//         const exportUrl = `/curriculum-versions/export/excel/filtered${params.toString() ? '?' + params.toString() : ''}`;

//         window.location.href = exportUrl;

//         setTimeout(() => {
//             toast.success('Export started successfully');
//         }, 500);
//     } catch (error) {
//         console.error('Export failed:', error);
//         toast.error('Failed to export curriculum versions');
//     } finally {
//         isExporting.value = false;
//     }
// };

// Column definitions - Fixed Badge warning by using function slots
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
        header: 'Version Code',
        accessorKey: 'version_code',
        enableSorting: true,
        cell: ({ row }) => {
            const cv = row.original;
            return h('div', { class: 'font-medium text-sm' }, cv.version_code || 'N/A');
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
                              h('div', { class: 'font-medium' }, program.name),
                              h(
                                  Badge,
                                  {
                                      variant: 'default',
                                  },
                                  () => program.code, // Function slot to fix warning
                              ),
                          ])
                        : null,
                    specialization ? h(Badge, { variant: 'outline', class: 'bg-blue-50 text-blue-600' }, () => [h('span', { class: 'font-mono' }, specialization.code), ' - ', specialization.name]) : null,
                ].filter(Boolean),
            );
        },
    },
    {
        header: 'Effective Semester',
        accessorKey: 'effective_from_semester.name',
        enableSorting: true,
        cell: ({ row }) => {
            const cv = row.original;
            const semester = cv.effective_from_semester;

            if (!semester) {
                return h('div', { class: 'text-gray-400 text-sm' }, 'Not set');
            }

            return h('div', { class: 'space-y-1' }, [h('div', { class: 'font-medium text-sm' }, semester.name), h(Badge, { variant: 'secondary', class: 'text-xs' }, () => semester.code)]);
        },
    },
    {
        header: 'Units',
        accessorKey: 'curriculum_units_count',
        enableSorting: false,
        cell: ({ row }) => {
            const cv = row.original;
            return h('div', { class: 'flex items-center gap-1' }, [h(Book, { class: 'h-3 w-3 text-gray-400' }), h('span', {}, `${cv.curriculum_units_count} units`)]);
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
    filtersState.value.per_page = pageSize;
    applyFilters(filtersState.value);
};

const navigateToCreate = () => {
    const createUrl = new URL('/curriculum-versions/create', window.location.origin);

    // Pass current filters if they exist
    if (filtersState.value.program_id) {
        createUrl.searchParams.set('program_id', filtersState.value.program_id);
    }
    if (filtersState.value.specialization_id) {
        createUrl.searchParams.set('specialization_id', filtersState.value.specialization_id);
    }

    router.visit(createUrl.toString());
};
</script>

<template>
    <Head title="Curriculum Versions" />
    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
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
    </div>

    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Curriculum Versions</h1>
        <div class="flex items-center gap-2">
            <!--            <Button @click="exportToExcel" variant="outline" :disabled="isExporting" class="flex items-center gap-2">-->
            <!--                <FileSpreadsheet class="h-4 w-4" />-->
            <!--                {{ isExporting ? 'Exporting...' : 'Export Excel' }}-->
            <!--            </Button>-->
            <!--            <Button @click="router.visit('/curriculum-versions/import')" variant="outline" class="flex items-center gap-2">-->
            <!--                <Upload class="h-4 w-4" />-->
            <!--                Import Excel-->
            <!--            </Button>-->

            <Button v-if="permission.can('create_curriculum_version')" size="sm" @click="navigateToCreate">
                <Plus class="mr-2 h-4 w-4" />
                Add Curriculum Version
            </Button>
        </div>
    </div>

    <!-- Enhanced Filters Section -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <!-- Search Input using DebouncedInput (following development standards) -->
        <div class="min-w-[200px] flex-1">
            <div class="relative">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <DebouncedInput v-model="filtersState.search" @debounced="handleSearch" placeholder="Search curriculum versions..." class="pl-9" :debounce="300" />
            </div>
        </div>

        <!-- Program Filter -->
        <div class="min-w-[180px]">
            <Select :model-value="displayProgramId" @update:model-value="updateProgramFilter">
                <SelectTrigger>
                    <SelectValue placeholder="All programs" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All programs</SelectItem>
                    <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ program.name }}</span>
                            <Badge variant="outline" class="text-xs">{{ program.code }}</Badge>
                        </div>
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Specialization Filter - Enhanced with better UX -->
        <div class="min-w-[200px]">
            <Select :model-value="displaySpecializationId" @update:model-value="updateSpecializationFilter" :disabled="!filtersState.program_id">
                <SelectTrigger :class="{ 'opacity-50': !filtersState.program_id }">
                    <SelectValue :placeholder="filtersState.program_id ? 'All specializations' : 'Select program first'" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All specializations</SelectItem>
                    <SelectItem v-for="specialization in filteredSpecializations" :key="specialization.id" :value="specialization.id.toString()">
                        <div class="flex items-center gap-2">
                            <Badge variant="outline" class="font-mono text-xs">{{ specialization.code }}</Badge>
                            <span>{{ specialization.name }}</span>
                        </div>
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Clear Filters Button -->
        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>

        <!-- Active Filters Indicator -->
        <div v-if="hasActiveFilters" class="text-muted-foreground text-sm">{{ Object.values(filtersState).filter(Boolean).length }} filter(s) active</div>
    </div>

    <!-- Data Table -->
    <div class="rounded-md border">
        <DataTable :data="data" :columns="columns">
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="viewCurriculumVersion(row.original)" title="View curriculum version">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View curriculum version</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <TooltipProvider v-if="permission.can('edit_curriculum_version')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="editCurriculumVersion(row.original)" title="Edit curriculum version">
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit curriculum version</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <TooltipProvider v-if="permission.can('create_curriculum_version')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="duplicateCurriculumVersion(row.original)" title="Duplicate curriculum version">
                                    <Copy class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Duplicate curriculum version</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <TooltipProvider v-if="permission.can('delete_curriculum_version')" :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="deleteCurriculumVersion(row.original)" title="Delete curriculum version">
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

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Curriculum Version</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete curriculum version <strong>{{ curriculumVersionToDelete?.version_code }}</strong
                    >? This action cannot be undone and will permanently remove the curriculum version and all its associated units.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="deleteDialogOpen = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" class="bg-red-600 hover:bg-red-700">Delete Curriculum Version</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Edit Modal -->
    <Dialog v-model:open="showEditModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Edit Curriculum Version</DialogTitle>
                <DialogDescription>Update curriculum version information.</DialogDescription>
            </DialogHeader>

            <Form
                v-if="curriculumVersionToEdit"
                :validation-schema="editFormSchema"
                :initial-values="{
                    program_id: curriculumVersionToEdit.program_id.toString(),
                    specialization_id: curriculumVersionToEdit.specialization_id?.toString() || '',
                    version_code: curriculumVersionToEdit.version_code,
                    semester_id: curriculumVersionToEdit.semester_id?.toString() || '',
                    notes: curriculumVersionToEdit.notes || '',
                }"
                @submit="onEditSubmit"
            >
                <div class="grid grid-cols-1 gap-4">
                    <FormField v-slot="{ componentField }" name="program_id">
                        <FormItem>
                            <FormLabel>Program *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a program" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                            {{ program.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="specialization_id">
                        <FormItem>
                            <FormLabel>Specialization</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a specialization" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="specialization in editFilteredSpecializations" :key="specialization.id" :value="specialization.id.toString()">
                                            {{ specialization.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="version_code">
                        <FormItem>
                            <FormLabel>Version Code *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., v1.0, 2023-S1" :maxlength="ValidationRules.curriculumVersion.versionCode.maxLength" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="semester_id">
                        <FormItem>
                            <FormLabel>Effective From Semester *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()"> {{ semester.name }} ({{ semester.code }}) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="notes">
                        <FormItem>
                            <FormLabel>Notes</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Enter any additional notes..." rows="4" :maxlength="ValidationRules.curriculumVersion.notes.maxLength" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <DialogFooter class="mt-4">
                    <Button type="button" variant="outline" @click="closeEditModal">Cancel</Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Updating...' : 'Update Version' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Duplicate Modal -->
    <Dialog v-model:open="showDuplicateModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Duplicate Curriculum Version</DialogTitle>
                <DialogDescription>
                    Create a copy of curriculum version <strong>{{ curriculumVersionToDuplicate?.version_code }}</strong> with a new version code.
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="curriculumVersionToDuplicate"
                :validation-schema="duplicateFormSchema"
                :initial-values="{
                    version_code: generateSuggestedVersionCode(curriculumVersionToDuplicate.version_code),
                    notes: curriculumVersionToDuplicate.notes || '',
                    include_curriculum_units: true,
                }"
                @submit="onDuplicateSubmit"
            >
                <div class="grid grid-cols-1 gap-4">
                    <FormField v-slot="{ componentField }" name="version_code">
                        <FormItem>
                            <FormLabel>New Version Code *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter new version code" :maxlength="20" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="notes">
                        <FormItem>
                            <FormLabel>Notes</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Enter any additional notes for the duplicate..." rows="4" :maxlength="ValidationRules.curriculumVersion.notes.maxLength" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="include_curriculum_units">
                        <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                            <FormControl>
                                <Checkbox v-bind="componentField" />
                            </FormControl>
                            <div class="space-y-1 leading-none">
                                <FormLabel>Include Curriculum Units</FormLabel>
                                <p class="text-muted-foreground text-[0.8rem]">Copy all {{ curriculumVersionToDuplicate.curriculum_units_count }} curriculum units from the original version to the duplicate.</p>
                            </div>
                        </FormItem>
                    </FormField>

                    <!-- Original Version Summary -->
                    <div class="bg-muted/50 rounded-lg border p-4">
                        <h4 class="mb-2 font-medium">Original Version Details</h4>
                        <div class="text-muted-foreground space-y-1 text-sm">
                            <div><strong>Program:</strong> {{ curriculumVersionToDuplicate.program?.name }} ({{ curriculumVersionToDuplicate.program?.code }})</div>
                            <div v-if="curriculumVersionToDuplicate.specialization"><strong>Specialization:</strong> {{ curriculumVersionToDuplicate.specialization.name }} ({{ curriculumVersionToDuplicate.specialization.code }})</div>
                            <div><strong>Effective From:</strong> {{ curriculumVersionToDuplicate.effective_from_semester?.name }}</div>
                            <div><strong>Curriculum Units:</strong> {{ curriculumVersionToDuplicate.curriculum_units_count }} units</div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-4">
                    <Button type="button" variant="outline" @click="closeDuplicateModal">Cancel</Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Duplicating...' : 'Duplicate Version' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
