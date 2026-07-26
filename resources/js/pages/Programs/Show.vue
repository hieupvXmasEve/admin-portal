<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, CheckCircle, Edit, Eye, Info, Plus, Trash2, Users } from 'lucide-vue-next';
import { Form, useForm as useVeeForm } from 'vee-validate';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Specialization {
    id: number;
    name: string;
    code?: string;
    description?: string;
    is_active: boolean;
    curriculum_versions_count: number;
}

interface Semester {
    id: number;
    name: string;
    code: string;
}

interface CurriculumVersion {
    id: number;
    version_code?: string;
    curriculum_units_count: number;
    created_at: string;
    specialization?: Specialization;
    effective_from_semester?: Semester;
}

interface Program {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
    specializations: Specialization[];
    curriculum_versions: CurriculumVersion[];
}

interface Stats {
    totalSpecializations: number;
    activeSpecializations: number;
    totalCurriculumVersions: number;
    programLevelVersions: number;
    specializationLevelVersions: number;
}

const props = defineProps<{
    program: Program;
    stats: Stats;
}>();

// API composable
const api = useApi();

// Filters and pagination
const specializationFilters = ref({
    search: '',
    page: 1,
});

// Computed filtered and paginated data
const filteredSpecializations = computed(() => {
    if (!specializationFilters.value.search) {
        return props.program.specializations;
    }
    return props.program.specializations.filter(
        (spec) =>
            spec.name.toLowerCase().includes(specializationFilters.value.search.toLowerCase()) ||
            (spec.code && spec.code.toLowerCase().includes(specializationFilters.value.search.toLowerCase())) ||
            (spec.description && spec.description.toLowerCase().includes(specializationFilters.value.search.toLowerCase())),
    );
});

const paginatedSpecializations = computed(() => {
    const start = (specializationFilters.value.page - 1) * 10;
    const end = start + 10;
    return {
        data: filteredSpecializations.value.slice(start, end),
        total: filteredSpecializations.value.length,
    };
});

// Specialization table columns
const specializationColumns: ColumnDef<Specialization>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => {
            const spec = row.original;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'flex items-center gap-2' }, [
                    h('span', { class: 'font-medium' }, spec.name),
                    spec.is_active ? h(Badge, { variant: 'secondary', class: 'border-green-200 bg-green-100 text-xs text-green-700' }, () => 'Active') : h(Badge, { variant: 'outline', class: 'text-xs' }, () => 'Inactive'),
                ]),
                spec.code ? h('div', { class: 'text-xs text-muted-foreground' }, `Code: ${spec.code}`) : null,
                spec.description ? h('div', { class: 'text-xs text-muted-foreground line-clamp-1' }, spec.description) : null,
            ]);
        },
    },
    {
        accessorKey: 'curriculum_versions_count',
        header: 'Versions',
        cell: ({ row }) => h('div', { class: 'text-left' }, row.original.curriculum_versions_count),
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const spec = row.original;
            return h('div', { class: 'flex gap-1' }, [
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => router.visit(`/specializations/${spec.id}`),
                    },
                    () => h(Eye, { class: 'h-4 w-4' }),
                ),
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => openEditModal(spec),
                    },
                    () => h(Edit, { class: 'h-4 w-4' }),
                ),
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => openDeleteDialog(spec),
                    },
                    () => h(Trash2, { class: 'h-4 w-4' }),
                ),
            ]);
        },
    },
];

// Filter handlers
const handleSpecializationSearch = (value: string | number) => {
    specializationFilters.value.search = String(value);
    specializationFilters.value.page = 1;
};

const handleSpecializationPageChange = (url: string) => {
    const urlParams = new URLSearchParams(url.split('?')[1] || '');
    const page = parseInt(urlParams.get('page') || '1');
    specializationFilters.value.page = page;
};

// Computed pagination data for DataPagination component
const specializationPaginationData = computed(() => ({
    from: (specializationFilters.value.page - 1) * 10 + 1,
    to: Math.min(specializationFilters.value.page * 10, paginatedSpecializations.value.total),
    total: paginatedSpecializations.value.total,
    current_page: specializationFilters.value.page,
    last_page: Math.ceil(paginatedSpecializations.value.total / 10),
    per_page: 10,
    prev_page_url: specializationFilters.value.page > 1 ? `?page=${specializationFilters.value.page - 1}` : null,
    next_page_url: specializationFilters.value.page < Math.ceil(paginatedSpecializations.value.total / 10) ? `?page=${specializationFilters.value.page + 1}` : null,
    links: [] as any[],
}));

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

// Preserve current query when navigating back to programs
const backToProgramsUrl = computed(() => {
    const search = typeof window !== 'undefined' ? window.location.search : '';
    return route('programs.index') + (search || '');
});

// Modal states
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteDialog = ref(false);
const selectedSpecialization = ref<Specialization | null>(null);
const isDeleting = ref(false);

// Validation schema
const specializationSchema = toTypedSchema(
    z.object({
        program_id: z.number().optional().default(props.program.id),
        name: z.string().min(1, 'Specialization name is required').max(255, 'Specialization name must not exceed 255 characters'),
        code: z
            .string()
            .min(1, 'Specialization code is required')
            .max(50, 'Specialization code must not exceed 50 characters')
            .regex(/^[A-Z0-9_-]+$/i, 'Specialization code can only contain letters, numbers, hyphens and underscores'),
        description: z.string().max(1000, 'Description must not exceed 1000 characters').optional().or(z.literal('')),
        is_active: z.boolean().default(true),
    }),
);

// VeeValidate forms
const createForm = useVeeForm({
    validationSchema: specializationSchema,
    initialValues: {
        program_id: props.program.id,
        name: '',
        code: '',
        description: '',
        is_active: true,
    },
});

const editForm = useVeeForm({
    validationSchema: specializationSchema,
    initialValues: {
        program_id: props.program.id,
        name: '',
        code: '',
        description: '',
        is_active: true,
    },
});

// Inertia forms for submission
const createInertiaForm = useForm({
    program_id: props.program.id,
    name: '',
    code: '',
    description: '',
    is_active: true,
});

const editInertiaForm = useForm({
    program_id: props.program.id,
    name: '',
    code: '',
    description: '',
    is_active: true,
});

// Remove the deleteForm since we'll use API directly

// Modal functions
const openCreateModal = () => {
    createForm.resetForm();
    createInertiaForm.reset();
    createInertiaForm.program_id = props.program.id;
    createInertiaForm.is_active = true;
    showCreateModal.value = true;
};

const openEditModal = (specialization: Specialization) => {
    selectedSpecialization.value = specialization;
    editForm.setValues({
        program_id: props.program.id,
        name: specialization.name,
        code: specialization.code || '',
        description: specialization.description || '',
        is_active: specialization.is_active,
    });
    Object.assign(editInertiaForm, {
        program_id: props.program.id,
        name: specialization.name,
        code: specialization.code || '',
        description: specialization.description || '',
        is_active: specialization.is_active,
    });
    showEditModal.value = true;
};

const openDeleteDialog = (specialization: Specialization) => {
    selectedSpecialization.value = specialization;
    showDeleteDialog.value = true;
};

const closeModals = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showDeleteDialog.value = false;
    selectedSpecialization.value = null;
};

const closeModal = () => {
    closeModals();
};

// Form submissions for VeeValidate Form components
const onCreateSubmit = (values: any) => {
    Object.assign(createInertiaForm, values);
    createInertiaForm.post(route('specializations.store') + '?source=program-show', {
        onSuccess: () => {
            closeModals();
            toast.success('Specialization created successfully');
            router.reload();
        },
        onError: (errors) => {
            console.error('Create form errors:', errors);
            toast.error('Failed to create specialization', {
                description: errors.code || errors.name || 'Failed to create specialization',
            });
        },
    });
};

const onEditSubmit = (values: any) => {
    if (!selectedSpecialization.value) return;

    Object.assign(editInertiaForm, values);
    editInertiaForm.put(route('specializations.update', selectedSpecialization.value.id) + '?source=program-show', {
        onSuccess: () => {
            closeModals();
            toast.success('Specialization updated successfully');
            router.reload();
        },
        onError: (errors) => {
            console.error('Edit form errors:', errors);
            toast.error('Failed to update specialization', {
                description: errors.code || errors.name || 'Failed to update specialization',
            });
        },
    });
};

const confirmDelete = async () => {
    if (!selectedSpecialization.value) return;

    isDeleting.value = true;

    try {
        const { data, error, statusCode } = await api.delete(`/api/specializations/${selectedSpecialization.value.id}`);

        if (statusCode.value === 200 && data.value?.success) {
            toast.success('Specialization deleted successfully');
            closeModals();
            // Reload the program data to reflect the deletion
            router.reload({ only: ['program', 'stats'] });
        } else {
            const errorMessage = data.value?.message || error.value || 'Failed to delete specialization';
            toast.error(errorMessage);
        }
    } catch (err) {
        console.error('Delete error:', err);
        toast.error('Failed to delete specialization');
    } finally {
        isDeleting.value = false;
    }
};
</script>

<template>
    <Head :title="`${program.name} Program`" />

    <!-- Compact Header with Stats -->
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold tracking-tight">{{ program.name }}</h1>
                </div>
                <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                    <span>Created: {{ formatDate(program.created_at) }}</span>
                    <span>Updated: {{ formatDate(program.updated_at) }}</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link :href="backToProgramsUrl">
                    <Button variant="outline">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Programs
                    </Button>
                </Link>
            </div>
        </div>

        <!-- Compact Stats Row -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
            <div class="bg-card rounded-lg border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold">{{ stats.totalSpecializations }}</p>
                        <p class="text-muted-foreground text-xs">Total Specializations</p>
                    </div>
                    <Users class="text-muted-foreground h-4 w-4" />
                </div>
            </div>
            <div class="bg-card rounded-lg border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold">{{ stats.activeSpecializations }}</p>
                        <p class="text-muted-foreground text-xs">Active</p>
                    </div>
                    <CheckCircle class="h-4 w-4 text-green-600" />
                </div>
            </div>
            <div class="bg-card rounded-lg border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-2xl font-bold">{{ program.specializations.length }}</p>
                        <p class="text-muted-foreground text-xs">Total Items</p>
                    </div>
                    <Info class="text-muted-foreground h-4 w-4" />
                </div>
            </div>
        </div>
    </div>

    <!-- Specializations Table -->
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Specializations
                    <Badge variant="secondary" class="ml-2">{{ paginatedSpecializations.data.length }}</Badge>
                </CardTitle>
                <Button variant="outline" size="sm" @click="openCreateModal">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Specialization
                </Button>
            </div>

            <!-- Search Filter -->
            <div class="mt-2">
                <DebouncedInput v-model="specializationFilters.search" @debounced="handleSpecializationSearch" placeholder="Search specializations..." class="max-w-sm" />
            </div>
        </CardHeader>
        <CardContent class="px-6">
            <div v-if="paginatedSpecializations.data.length === 0" class="py-8 text-center">
                <Users class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-4 text-sm font-medium">No specializations found</h3>
                <p class="text-muted-foreground mt-2 text-sm">
                    {{ specializationFilters.search ? 'Try adjusting your search terms.' : 'Get started by creating a new specialization.' }}
                </p>
                <div class="mt-6" v-if="!specializationFilters.search">
                    <Button @click="openCreateModal">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Specialization
                    </Button>
                </div>
            </div>

            <div v-else>
                <DataTable :data="paginatedSpecializations.data" :columns="specializationColumns" :loading="false" class="border-0" />
                <div class="border-t p-4" v-if="paginatedSpecializations.total > 10">
                    <DataPagination :pagination-data="specializationPaginationData" @navigate="handleSpecializationPageChange" />
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Create Specialization Modal -->
    <Dialog v-model:open="showCreateModal">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Add New Specialization</DialogTitle>
                <DialogDescription>Create a new specialization for {{ program.name }}.</DialogDescription>
            </DialogHeader>

            <Form :validation-schema="specializationSchema" @submit="onCreateSubmit">
                <div class="grid grid-cols-2 gap-4">
                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel for="create-code">Specialization Code *</FormLabel>
                            <FormControl>
                                <Input id="create-code" placeholder="e.g., AI, SE, DB" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel for="create-name">Specialization Name *</FormLabel>
                            <FormControl>
                                <Input id="create-name" placeholder="e.g., Artificial Intelligence" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="col-span-2">
                        <FormField v-slot="{ componentField }" name="description">
                            <FormItem>
                                <FormLabel for="create-description">Description</FormLabel>
                                <FormControl>
                                    <Textarea id="create-description" placeholder="Specialization description (optional)" rows="3" v-bind="componentField" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="col-span-2">
                        <FormField v-slot="{ componentField }" name="is_active">
                            <FormItem class="flex flex-row items-center justify-between rounded-lg border p-4">
                                <div class="space-y-0.5">
                                    <FormLabel class="text-base">Active Status</FormLabel>
                                    <div class="text-muted-foreground text-sm">Enable this specialization for enrollment</div>
                                </div>
                                <FormControl>
                                    <Switch :checked="componentField.modelValue" @update:checked="componentField['onUpdate:modelValue']" />
                                </FormControl>
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeModal">Cancel</Button>
                    <Button type="submit" :disabled="createInertiaForm.processing">
                        {{ createInertiaForm.processing ? 'Creating...' : 'Create Specialization' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Edit Specialization Modal -->
    <Dialog v-model:open="showEditModal">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Edit Specialization</DialogTitle>
                <DialogDescription>Edit the details of the selected specialization.</DialogDescription>
            </DialogHeader>

            <Form
                :validation-schema="specializationSchema"
                :initial-values="{
                    program_id: props.program.id,
                    name: selectedSpecialization?.name || '',
                    code: selectedSpecialization?.code || '',
                    description: selectedSpecialization?.description || '',
                    is_active: selectedSpecialization?.is_active || true,
                }"
                @submit="onEditSubmit"
            >
                <div class="grid grid-cols-2 gap-4">
                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel for="edit-code">Specialization Code *</FormLabel>
                            <FormControl>
                                <Input id="edit-code" placeholder="e.g., AI, SE, DB" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel for="edit-name">Specialization Name *</FormLabel>
                            <FormControl>
                                <Input id="edit-name" placeholder="e.g., Artificial Intelligence" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="col-span-2">
                        <FormField v-slot="{ componentField }" name="description">
                            <FormItem>
                                <FormLabel for="edit-description">Description</FormLabel>
                                <FormControl>
                                    <Textarea id="edit-description" placeholder="Specialization description (optional)" rows="3" v-bind="componentField" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="col-span-2">
                        <FormField v-slot="{ componentField }" name="is_active">
                            <FormItem class="flex flex-row items-center justify-between rounded-lg border p-4">
                                <div class="space-y-0.5">
                                    <FormLabel class="text-base">Active Status</FormLabel>
                                    <div class="text-muted-foreground text-sm">Enable this specialization for enrollment</div>
                                </div>
                                <FormControl>
                                    <Switch :checked="componentField.modelValue" @update:checked="componentField['onUpdate:modelValue']" />
                                </FormControl>
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeModal">Cancel</Button>
                    <Button type="submit" :disabled="editInertiaForm.processing">
                        {{ editInertiaForm.processing ? 'Updating...' : 'Update Specialization' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Delete Specialization Confirmation Dialog -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Specialization</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete specialization <strong>{{ selectedSpecialization?.name }}</strong
                    >? <br /><br />
                    This action cannot be undone and will permanently remove this specialization from the system.
                    <span v-if="selectedSpecialization?.curriculum_versions_count && selectedSpecialization.curriculum_versions_count > 0" class="text-destructive">
                        <br /><br />
                        <strong>Warning:</strong> This specialization has {{ selectedSpecialization.curriculum_versions_count }} curriculum versions that will also be affected.
                    </span>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isDeleting">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" :disabled="isDeleting" class="bg-destructive hover:bg-destructive/90 text-white">
                    {{ isDeleting ? 'Deleting...' : 'Delete Specialization' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
