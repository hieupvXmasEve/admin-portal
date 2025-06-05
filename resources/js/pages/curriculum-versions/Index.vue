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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';
import { ValidationRules } from '@/types/validation';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { useDebounceFn } from '@vueuse/core';
import { Book, Edit, Eye, FileSpreadsheet, Plus, Search, Trash2, Upload, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref } from 'vue';
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
    curriculum_units_count: number;
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
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    statistics: Statistics;
    programs: Array<{ id: number; name: string; code: string }>;
    specializations: Array<{ id: number; name: string; code: string; program_id: number }>;
    semesters?: Array<{ id: number; name: string; code: string }>;
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
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

// Computed specializations based on selected program
const filteredSpecializations = computed(() => {
    if (!filters.value.program_id) return props.specializations;
    return props.specializations.filter((spec) => spec.program_id.toString() === filters.value.program_id);
});

// Computed display values for select components
const displayProgramId = computed(() => filters.value.program_id || 'all');
const displaySpecializationId = computed(() => filters.value.specialization_id || 'all');

// Delete dialog state
const deleteDialogOpen = ref(false);
const curriculumVersionToDelete = ref<CurriculumVersion | null>(null);

// Edit modal state
const showEditModal = ref(false);
const curriculumVersionToEdit = ref<CurriculumVersion | null>(null);

// Define validation schema for edit form
const editFormSchema = toTypedSchema(
    z.object({
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().min(1, 'Specialization is required'),
        version_code: z
            .string()
            .min(ValidationRules.curriculumVersion.versionCode.minLength, 'Version code is required')
            .max(ValidationRules.curriculumVersion.versionCode.maxLength, 'Version code cannot exceed 50 characters'),
        semester_id: z.string().min(1, 'Semester is required'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
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

// Permission check function
const can = (permission: string) => {
    const permissions = (page.props as any).permissions || [];
    return permissions.includes(permission);
};

// Action functions
const editCurriculumVersion = (curriculumVersion: CurriculumVersion) => {
    curriculumVersionToEdit.value = curriculumVersion;
    showEditModal.value = true;
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

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
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

// Debounced filter functions
const debouncedApplyFilters = useDebounceFn((newFilters) => {
    applyFilters(newFilters);
}, 500);

const updateSearchFilter = (value: string | number) => {
    filters.value.search = String(value);
    debouncedApplyFilters(filters.value);
};

const updateProgramFilter = (value: any) => {
    filters.value.program_id = value === 'all' ? '' : String(value || '');
    filters.value.specialization_id = ''; // Reset specialization when program changes
    applyFilters(filters.value);
};

const updateSpecializationFilter = (value: any) => {
    filters.value.specialization_id = value === 'all' ? '' : String(value || '');
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        program_id: '',
        specialization_id: '',
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
    return filters.value.search || filters.value.program_id || filters.value.specialization_id;
});

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
                                  program.code,
                              ),
                          ])
                        : null,
                    specialization
                        ? h(Badge, { variant: 'outline', class: 'bg-blue-50 text-blue-600' }, [
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
        header: 'Units',
        accessorKey: 'curriculum_units_count',
        enableSorting: false,
        cell: ({ row }) => {
            const cv = row.original;
            return h('div', { class: 'flex items-center gap-1' }, [
                h(Book, { class: 'h-3 w-3 text-gray-400' }),
                h('span', {}, `${cv.curriculum_units_count} units`),
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

const navigateToCreate = () => {
    const createUrl = new URL('/curriculum-versions/create', window.location.origin);

    // Pass current filters if they exist
    if (filters.value.program_id) {
        createUrl.searchParams.set('program_id', filters.value.program_id);
    }
    if (filters.value.specialization_id) {
        createUrl.searchParams.set('specialization_id', filters.value.specialization_id);
    }

    router.visit(createUrl.toString());
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

                    <Button v-if="can('create_curriculum_version')" size="sm" @click="navigateToCreate">
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
                    <Select :model-value="displayProgramId" @update:model-value="updateProgramFilter">
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

                <div class="min-w-[150px]">
                    <Select :model-value="displaySpecializationId" @update:model-value="updateSpecializationFilter" :disabled="!filters.program_id">
                        <SelectTrigger>
                            <SelectValue placeholder="All specializations" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All specializations</SelectItem>
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

                <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear Filters
                </Button>
            </div>

            <!-- Data Table -->
            <div class="rounded-md border">
                <DataTable :data="data" :columns="columns">
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
    </AppLayout>

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
                            <FormLabel>Specialization *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a specialization" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="specialization in editFilteredSpecializations"
                                            :key="specialization.id"
                                            :value="specialization.id.toString()"
                                        >
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
                                <Input
                                    v-bind="componentField"
                                    placeholder="e.g., v1.0, 2023-S1"
                                    :maxlength="ValidationRules.curriculumVersion.versionCode.maxLength"
                                />
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
                                        <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                            {{ semester.name }} ({{ semester.code }})
                                        </SelectItem>
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
                                <Textarea
                                    v-bind="componentField"
                                    placeholder="Enter any additional notes..."
                                    rows="4"
                                    :maxlength="ValidationRules.curriculumVersion.notes.maxLength"
                                />
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
</template>
