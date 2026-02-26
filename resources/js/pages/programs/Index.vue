<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { Edit, Eye, Plus, Trash2, X } from 'lucide-vue-next';
import { Form, useForm as useVeeForm } from 'vee-validate';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Program {
    id: number;
    name: string;
    code: string;
    description?: string;
    specializations_count: number;
    curriculum_versions_count: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    programs: PaginatedResponse<Program>;
    filters?: {
        search?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    errors?: any;
}>();

// Reactive data
const data = computed(() => props.programs.data);

// Filter state - Initialize with props or defaults
const filters = ref({
    search: props.filters?.search || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

// Modal states
const showCreateModal = ref(false);
const showEditModal = ref(false);
const deleteDialogOpen = ref(false);
const selectedProgram = ref<Program | null>(null);

// Validation schema
const programSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Program name is required').max(255, 'Program name must not exceed 255 characters'),
        code: z
            .string()
            .min(1, 'Program code is required')
            .max(10, 'Program code must not exceed 10 characters')
            .regex(/^[A-Z0-9_-]+$/i, 'Program code can only contain letters, numbers, hyphens and underscores'),
        description: z.string().max(1000, 'Description must not exceed 1000 characters').optional().or(z.literal('')),
    }),
);

// VeeValidate forms
const createForm = useVeeForm({
    validationSchema: programSchema,
    initialValues: {
        name: '',
        code: '',
        description: '',
    },
});

const editForm = useVeeForm({
    validationSchema: programSchema,
    initialValues: {
        name: '',
        code: '',
        description: '',
    },
});

// Inertia forms for submission
const createInertiaForm = useForm({
    name: '',
    code: '',
    description: '',
});

const editInertiaForm = useForm({
    name: '',
    code: '',
    description: '',
});

const deleteForm = useForm({});

// Modal functions
const openCreateModal = () => {
    createForm.resetForm();
    createInertiaForm.reset();
    showCreateModal.value = true;
};

const openEditModal = (program: Program) => {
    selectedProgram.value = program;
    editForm.setValues({
        name: program.name,
        code: program.code,
        description: program.description || '',
    });
    Object.assign(editInertiaForm, {
        name: program.name,
        code: program.code,
        description: program.description || '',
    });
    showEditModal.value = true;
};

const openDeleteModal = (program: Program) => {
    selectedProgram.value = program;
    deleteDialogOpen.value = true;
};

const closeModals = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    deleteDialogOpen.value = false;
    selectedProgram.value = null;
};

// Form submissions for VeeValidate Form components
const onCreateSubmit = (values: any) => {
    console.log('Create form submit triggered with values:', values);
    Object.assign(createInertiaForm, values);
    createInertiaForm.post(route('programs.store'), {
        onSuccess: () => {
            closeModals();
            toast.success('Program created successfully');
        },
        onError: (errors) => {
            console.error('Create form errors:', errors);
            toast.error('Failed to create program', {
                description: errors.code || errors.name || 'Failed to create program',
            });
        },
    });
};

const onEditSubmit = (values: any) => {
    if (!selectedProgram.value) return;
    console.log('Edit form submit triggered with values:', values);

    Object.assign(editInertiaForm, values);
    editInertiaForm.put(route('programs.update', selectedProgram.value.id), {
        onSuccess: () => {
            closeModals();
            toast.success('Program updated successfully');
        },
        onError: (errors) => {
            console.error('Edit form errors:', errors);
            toast.error('Failed to update program', {
                description: errors.code || errors.name || 'Failed to update program',
            });
        },
    });
};

const submitDelete = () => {
    if (!selectedProgram.value) return;

    deleteForm.delete(route('programs.destroy', selectedProgram.value.id), {
        onSuccess: () => {
            closeModals();
            toast.success('Program deleted successfully');
        },
        onError: () => {
            toast.error('Failed to delete program');
        },
    });
};

// View program function
const viewProgram = (program: Program) => {
    const search = typeof window !== 'undefined' ? window.location.search : '';
    router.visit(`${route('programs.show', program.id)}${search || ''}`);
};

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/programs${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['programs', 'filters'],
    });
};

// Search handler for DebouncedInput
const handleSearch = (value: string | number) => {
    console.log('Search value:', value);
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    router.visit('/programs', {
        preserveState: true,
        preserveScroll: true,
        only: ['programs', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search;
});

// Column definitions
const columns: ColumnDef<Program>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.programs.current_page;
            const perPage = props.programs.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Code',
        accessorKey: 'code',
        enableSorting: true,
        cell: ({ row }) => {
            const program = row.original;
            return h('code', { class: 'bg-gray-100 px-2 py-1 rounded text-sm font-mono' }, program.code);
        },
    },
    {
        header: 'Program Name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => {
            const program = row.original;
            return h('div', { class: 'font-medium' }, program.name);
        },
    },
    {
        header: 'Description',
        accessorKey: 'description',
        enableSorting: false,
        cell: ({ row }) => {
            const description = row.original.description;
            return description ? h('div', { class: 'text-sm text-gray-600 max-w-xs truncate' }, description) : h('span', { class: 'text-gray-400' }, 'No description');
        },
    },
    {
        header: 'Specializations',
        accessorKey: 'specializations_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.specializations_count;
            return count > 0 ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800' }, count) : h('span', { class: 'text-gray-400' }, 'None');
        },
    },
    {
        header: 'Curriculum Versions',
        accessorKey: 'curriculum_versions_count',
        enableSorting: false,
        cell: ({ row }) => {
            const count = row.original.curriculum_versions_count;
            return count > 0 ? h('span', { class: 'inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800' }, count) : h('span', { class: 'text-gray-400' }, 'None');
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
        only: ['programs'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
</script>
<template>

    <Head title="Programs" />
    <!-- Header with Add Program Button -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Programs</h1>
        <div class="flex items-center gap-2">
            <Button size="sm" @click="openCreateModal">
                <Plus class="mr-2 h-4 w-4" />
                Add Program
            </Button>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <DebouncedInput placeholder="Search programs..." v-model="filters.search" @debounced="handleSearch" />
        </div>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns">
        <template #cell-actions="{ row }">
            <div class="flex items-center gap-2">
                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="viewProgram(row.original)" title="View program">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>View program</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="openEditModal(row.original)" title="Edit program">
                                <Edit class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Edit program</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="openDeleteModal(row.original)"
                                title="Delete program">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Delete program</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination :pagination-data="programs" @navigate="handlePaginationNavigate"
        @page-size-change="handlePageSizeChange" />

    <!-- Create Modal -->
    <Dialog v-model:open="showCreateModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Add New Program</DialogTitle>
                <DialogDescription>Create a new academic program.</DialogDescription>
            </DialogHeader>

            <Form :validation-schema="programSchema" @submit="onCreateSubmit">
                <div class="grid grid-cols-2 gap-4">
                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel for="create-code">Program Code *</FormLabel>
                            <FormControl>
                                <Input id="create-code" placeholder="e.g., CS, ENG, BUS" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel for="create-name">Program Name *</FormLabel>
                            <FormControl>
                                <Input id="create-name" placeholder="e.g., Computer Science" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="col-span-2">
                        <FormField v-slot="{ componentField }" name="description">
                            <FormItem>
                                <FormLabel for="create-description">Description</FormLabel>
                                <FormControl>
                                    <Textarea id="create-description" placeholder="Program description (optional)"
                                        rows="3" v-bind="componentField" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeModals">Cancel</Button>
                    <Button type="submit" :disabled="createInertiaForm.processing">
                        {{ createInertiaForm.processing ? 'Creating...' : 'Create Program' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Edit Modal -->
    <Dialog v-model:open="showEditModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Edit Program</DialogTitle>
                <DialogDescription>Update program information.</DialogDescription>
            </DialogHeader>

            <Form :validation-schema="programSchema" :initial-values="{
                name: selectedProgram?.name || '',
                code: selectedProgram?.code || '',
                description: selectedProgram?.description || '',
            }" @submit="onEditSubmit">
                <div class="grid grid-cols-2 gap-4">
                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel for="edit-code">Program Code *</FormLabel>
                            <FormControl>
                                <Input id="edit-code" disabled placeholder="e.g., CS, ENG, BUS"
                                    v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel for="edit-name">Program Name *</FormLabel>
                            <FormControl>
                                <Input id="edit-name" placeholder="e.g., Computer Science" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="col-span-2">
                        <FormField v-slot="{ componentField }" name="description">
                            <FormItem>
                                <FormLabel for="edit-description">Description</FormLabel>
                                <FormControl>
                                    <Textarea id="edit-description" placeholder="Program description (optional)"
                                        rows="3" v-bind="componentField" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <DialogFooter class="mt-4">
                    <Button type="button" variant="outline" @click="closeModals">Cancel</Button>
                    <Button type="submit" :disabled="editInertiaForm.processing">
                        {{ editInertiaForm.processing ? 'Updating...' : 'Update Program' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Program</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete program <strong>{{ selectedProgram?.name }}</strong>? This action
                    cannot
                    be undone and will permanently remove the program from the system.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeModals">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="submitDelete" :disabled="deleteForm.processing"
                    class="bg-red-600 hover:bg-red-700">
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete Program' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
