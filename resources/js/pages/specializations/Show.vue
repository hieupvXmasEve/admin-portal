<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { CurriculumVersion, Program, Semester, Specialization } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, BookOpen, Edit, Eye, Plus, Settings, Target, Trash2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Statistics {
    curriculum_versions_count: number;
    program_level_versions: number;
    specialization_level_versions: number;
}

interface Props {
    specialization: Specialization & {
        curriculum_versions?: CurriculumVersion[];
        program?: Program;
    };
    statistics: Statistics;
    programs?: Program[];
    semesters?: Semester[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Specializations',
        href: '/specializations',
    },
    {
        title: props.specialization.name,
        href: `/specializations/${props.specialization.id}`,
    },
];

// API composable
const api = useApi();

// Modal dialog state
const isDialogOpen = ref(false);

// Delete confirmation dialog state
const isDeleteDialogOpen = ref(false);
const versionToDelete = ref<CurriculumVersion | null>(null);
const isDeleting = ref(false);

// Edit modal state
const showEditModal = ref(false);
const curriculumVersionToEdit = ref<CurriculumVersion | null>(null);

// Define validation schema for curriculum version creation
const formSchema = toTypedSchema(
    z.object({
        version_code: z
            .string()
            .min(ValidationRules.curriculumVersion.versionCode.minLength, 'Version code is required')
            .max(ValidationRules.curriculumVersion.versionCode.maxLength, 'Version code cannot exceed 50 characters'),
        semester_id: z.string().min(1, 'Semester is required'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
    }),
);

// Define validation schema for edit form
const editFormSchema = toTypedSchema(
    z.object({
        version_code: z
            .string()
            .min(ValidationRules.curriculumVersion.versionCode.minLength, 'Version code is required')
            .max(ValidationRules.curriculumVersion.versionCode.maxLength, 'Version code cannot exceed 50 characters'),
        semester_id: z.string().min(1, 'Semester is required'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
    }),
);

const { handleSubmit, isSubmitting, resetForm } = useForm({
    validationSchema: formSchema,
    initialValues: {
        version_code: '',
        semester_id: '',
        notes: '',
    },
});

// Form setup for edit modal
const { isSubmitting: isEditSubmitting } = useForm({
    validationSchema: editFormSchema,
});

// Filters and pagination
const curriculumFilters = ref({
    search: '',
    page: 1,
});

// Computed filtered and paginated data
const filteredCurriculumVersions = computed(() => {
    const versions = props.specialization.curriculum_versions || [];
    if (!curriculumFilters.value.search) {
        return versions;
    }
    return versions.filter(
        (version) =>
            (version.version_code && version.version_code.toLowerCase().includes(curriculumFilters.value.search.toLowerCase())) ||
            (version.effective_from_semester?.name &&
                version.effective_from_semester.name.toLowerCase().includes(curriculumFilters.value.search.toLowerCase())),
    );
});

const paginatedCurriculumVersions = computed(() => {
    const start = (curriculumFilters.value.page - 1) * 10;
    const end = start + 10;
    return {
        data: filteredCurriculumVersions.value.slice(start, end),
        total: filteredCurriculumVersions.value.length,
    };
});

// Open delete confirmation modal
const openDeleteModal = (versionId: number) => {
    const version = props.specialization.curriculum_versions?.find((v) => v.id === versionId);
    if (version) {
        versionToDelete.value = version;
        isDeleteDialogOpen.value = true;
    }
};

// Edit modal functions
const editCurriculumVersion = (versionId: number) => {
    const version = props.specialization.curriculum_versions?.find((v) => v.id === versionId);
    if (version) {
        curriculumVersionToEdit.value = version;
        showEditModal.value = true;
    }
};

const closeEditModal = () => {
    showEditModal.value = false;
    curriculumVersionToEdit.value = null;
};

const onEditSubmit = async (values: any) => {
    if (!curriculumVersionToEdit.value) return;

    const submitData = {
        version_code: values.version_code,
        semester_id: values.semester_id ? parseInt(values.semester_id) : null,
        notes: values.notes || null,
    };

    try {
        const { data, error, statusCode } = await api.put(`/api/curriculum-versions/${curriculumVersionToEdit.value.id}`, submitData);

        if (statusCode.value === 200 && data.value?.success) {
            toast.success('Curriculum version updated successfully');
            closeEditModal();
            // Reload the specialization data to reflect the update
            router.reload({ only: ['specialization', 'statistics'] });
        } else {
            const errorMessage = data.value?.message || error.value || 'Failed to update curriculum version';
            toast.error(errorMessage);
        }
    } catch (err) {
        console.error('Update error:', err);
        toast.error('Failed to update curriculum version');
    }
};

// Confirm delete curriculum version
const confirmDelete = async () => {
    if (!versionToDelete.value) return;

    isDeleting.value = true;

    try {
        const { data, error, statusCode } = await api.delete(`/api/curriculum-versions/${versionToDelete.value.id}`);

        if (statusCode.value === 200 && data.value?.success) {
            toast.success('Curriculum version deleted successfully');
            isDeleteDialogOpen.value = false;
            versionToDelete.value = null;
            // Reload the specialization data to reflect the deletion
            router.reload({ only: ['specialization', 'statistics'] });
        } else {
            const errorMessage = data.value?.message || error.value || 'Failed to delete curriculum version';
            toast.error(errorMessage);
        }
    } catch (err) {
        console.error('Delete error:', err);
        toast.error('Failed to delete curriculum version');
    } finally {
        isDeleting.value = false;
    }
};

// Curriculum version table columns
const curriculumVersionColumns: ColumnDef<CurriculumVersion>[] = [
    {
        accessorKey: 'version_code',
        header: 'Version',
        cell: ({ row }) => {
            const version = row.original;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'font-medium' }, version.version_code || 'Unnamed Version'),
                h(Badge, { variant: 'outline', class: 'text-xs' }, () => 'Specialization Level'),
                version.effective_from_semester
                    ? h('div', { class: 'text-xs text-muted-foreground' }, `From: ${version.effective_from_semester.name}`)
                    : null,
            ]);
        },
    },
    {
        accessorKey: 'curriculum_units_count',
        header: 'Units',
        cell: ({ row }) => h('div', { class: 'text-center' }, row.original.curriculum_units_count || 0),
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        cell: ({ row }) => h('div', { class: 'text-xs text-muted-foreground' }, formatDate(row.original.created_at)),
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const version = row.original;
            return h('div', { class: 'flex gap-1' }, [
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => router.visit(`/curriculum-versions/${version.id}`),
                    },
                    () => h(Eye, { class: 'h-4 w-4' }),
                ),
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => editCurriculumVersion(version.id),
                    },
                    () => h(Edit, { class: 'h-4 w-4' }),
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'ghost',
                        disabled: false,
                        onClick: () => openDeleteModal(version.id),
                    },
                    [h(Trash2, { class: 'h-4 w-4' })],
                ),
            ]);
        },
    },
];

// Filter handlers
const handleCurriculumSearch = (value: string | number) => {
    curriculumFilters.value.search = String(value);
    curriculumFilters.value.page = 1;
};

const handleCurriculumPageChange = (url: string) => {
    const urlParams = new URLSearchParams(url.split('?')[1] || '');
    const page = parseInt(urlParams.get('page') || '1');
    curriculumFilters.value.page = page;
};

// Computed pagination data for DataPagination component
const curriculumPaginationData = computed(() => ({
    from: (curriculumFilters.value.page - 1) * 10 + 1,
    to: Math.min(curriculumFilters.value.page * 10, paginatedCurriculumVersions.value.total),
    total: paginatedCurriculumVersions.value.total,
    current_page: curriculumFilters.value.page,
    last_page: Math.ceil(paginatedCurriculumVersions.value.total / 10),
    per_page: 10,
    prev_page_url: curriculumFilters.value.page > 1 ? `?page=${curriculumFilters.value.page - 1}` : null,
    next_page_url:
        curriculumFilters.value.page < Math.ceil(paginatedCurriculumVersions.value.total / 10) ? `?page=${curriculumFilters.value.page + 1}` : null,
    links: [] as any[],
}));

// Form submission handler
const onSubmit = handleSubmit(async (values) => {
    console.log(values);
    const submitData = {
        program_id: props.specialization.program_id,
        specialization_id: props.specialization.id,
        version_code: values.version_code,
        semester_id: parseInt(values.semester_id),
        notes: values.notes || null,
    };

    const { data, error, statusCode } = await api.post<CurriculumVersion>('/api/curriculum-versions', submitData);

    if (statusCode.value === 201 && data.value?.success) {
        toast.success('Curriculum version created successfully');
        isDialogOpen.value = false;
        resetForm();
        // Reload only the specialization data to show the new curriculum version
        router.reload({ only: ['specialization', 'statistics'] });
    } else {
        const errorMessage = data.value?.message || error.value || 'Failed to create curriculum version';
        toast.error(errorMessage);
    }
});

// Helper function to open create dialog
const openCreateDialog = () => {
    resetForm();
    isDialogOpen.value = true;
};

// Helper functions
const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`${specialization.name} Specialization`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex flex-col gap-6 p-4">
            <!-- Compact Header with Stats -->
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-bold tracking-tight">{{ specialization.name }}</h1>
                            <Badge v-if="specialization.is_active" variant="secondary" class="border-green-200 bg-green-100 text-green-700">
                                Active
                            </Badge>
                            <Badge v-else variant="outline">Inactive</Badge>
                        </div>
                        <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                            <span v-if="specialization.code">Code: {{ specialization.code }}</span>
                            <span>Program: {{ specialization.program?.name }}</span>
                            <span>Created: {{ formatDate(specialization.created_at) }}</span>
                            <span>Updated: {{ formatDate(specialization.updated_at) }}</span>
                        </div>
                        <p v-if="specialization.description" class="text-muted-foreground text-sm">{{ specialization.description }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('specializations.edit', { specialization: specialization.id })">
                            <Button variant="outline">
                                <Edit class="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                        <Link :href="route('specializations.index')">
                            <Button variant="outline">
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Back to Specializations
                            </Button>
                        </Link>
                    </div>
                </div>

                <!-- Compact Stats Row -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <div class="bg-card rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-2xl font-bold">{{ statistics.curriculum_versions_count }}</p>
                                <p class="text-muted-foreground text-xs">Total Versions</p>
                            </div>
                            <BookOpen class="text-muted-foreground h-4 w-4" />
                        </div>
                    </div>
                    <div class="bg-card rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-2xl font-bold">{{ statistics.program_level_versions }}</p>
                                <p class="text-muted-foreground text-xs">Program Level</p>
                            </div>
                            <Settings class="text-muted-foreground h-4 w-4" />
                        </div>
                    </div>
                    <div class="bg-card rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-2xl font-bold">{{ statistics.specialization_level_versions }}</p>
                                <p class="text-muted-foreground text-xs">Specialization Level</p>
                            </div>
                            <Target class="text-muted-foreground h-4 w-4" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Curriculum Versions Table -->
            <Card>
                <CardHeader class="pb-4">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <BookOpen class="h-5 w-5" />
                            Curriculum Versions
                            <Badge variant="secondary" class="ml-2">{{ paginatedCurriculumVersions.data.length }}</Badge>
                        </CardTitle>

                        <!-- Dialog for Add Version -->
                        <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
                            <DialogTrigger as-child>
                                <Button variant="outline" size="sm" @click="openCreateDialog">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Version
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-[600px]">
                                <DialogHeader>
                                    <DialogTitle>Create Curriculum Version</DialogTitle>
                                    <DialogDescription>
                                        Add a new curriculum version for {{ specialization.name }} specialization.
                                    </DialogDescription>
                                </DialogHeader>

                                <form @submit="onSubmit" class="space-y-4">
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
                                                    rows="3"
                                                    :maxlength="ValidationRules.curriculumVersion.notes.maxLength"
                                                />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    </FormField>

                                    <DialogFooter>
                                        <Button type="button" variant="outline" @click="isDialogOpen = false"> Cancel </Button>
                                        <Button type="submit" :disabled="isSubmitting">
                                            {{ isSubmitting ? 'Creating...' : 'Create Version' }}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>

                    <!-- Search Filter -->
                    <div class="mt-4">
                        <DebouncedInput
                            v-model="curriculumFilters.search"
                            @debounced="handleCurriculumSearch"
                            placeholder="Search curriculum versions..."
                            class="max-w-sm"
                        />
                    </div>
                </CardHeader>
                <CardContent class="px-4">
                    <div v-if="paginatedCurriculumVersions.data.length === 0" class="py-8 text-center">
                        <BookOpen class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-4 text-sm font-medium">No curriculum versions found</h3>
                        <p class="text-muted-foreground mt-2 text-sm">
                            {{
                                curriculumFilters.search ? 'Try adjusting your search terms.' : 'Create curriculum versions to define the structure.'
                            }}
                        </p>
                        <div class="mt-6" v-if="!curriculumFilters.search">
                            <Button @click="openCreateDialog">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Curriculum Version
                            </Button>
                        </div>
                    </div>

                    <div v-else>
                        <DataTable :data="paginatedCurriculumVersions.data" :columns="curriculumVersionColumns" :loading="false" class="border-0" />
                        <div class="border-t p-4" v-if="paginatedCurriculumVersions.total > 10">
                            <DataPagination :pagination-data="curriculumPaginationData" @navigate="handleCurriculumPageChange" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="isDeleteDialogOpen" @update:open="isDeleteDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Curriculum Version</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete curriculum version
                        <strong>{{ versionToDelete?.version_code }}</strong
                        >? <br /><br />
                        This action cannot be undone and will permanently remove this curriculum version from the system.
                        <span v-if="versionToDelete?.curriculum_units_count && versionToDelete.curriculum_units_count > 0" class="text-destructive">
                            <br /><br />
                            <strong>Warning:</strong> This version has {{ versionToDelete.curriculum_units_count }} curriculum units that will also be
                            affected.
                        </span>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="isDeleting" class="cursor-pointer">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="bg-destructive hover:bg-destructive/90 cursor-pointer text-white"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </AlertDialogAction>
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
                    version_code: curriculumVersionToEdit.version_code,
                    semester_id: curriculumVersionToEdit.semester_id?.toString() || '',
                    notes: curriculumVersionToEdit.notes || '',
                }"
                @submit="onEditSubmit"
            >
                <div class="grid grid-cols-1 gap-4">
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
                    <Button type="submit" :disabled="isEditSubmitting">
                        {{ isEditSubmitting ? 'Updating...' : 'Update Version' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
