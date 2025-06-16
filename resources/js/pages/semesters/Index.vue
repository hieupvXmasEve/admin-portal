<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { fromDate } from '@internationalized/date';
import type { ColumnDef } from '@tanstack/vue-table';
import { Edit, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';

interface Semester {
    id: number;
    code: string;
    name: string;
    start_date: string;
    end_date: string;
    enrollment_start_date: string | null;
    enrollment_end_date: string | null;
    is_active: boolean;
    is_archived: boolean;
    created_at: string;
    updated_at: string;
}

interface SemesterData {
    data: Semester[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: any[];
}

interface Props {
    semesters: SemesterData;
    filters: {
        search: string | null;
        name: string | null;
        year: string | null;
        is_active: boolean | null;
        is_archived: boolean | null;
    };
    errors: any;
}

const props = defineProps<Props>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'List Semesters',
        href: '/semesters',
    },
];

// Reactive filters
const search = ref(props.filters.search || '');
const nameFilter = ref(props.filters.name || '');
const yearFilter = ref(props.filters.year || '');
const isActiveFilter = ref(props.filters.is_active);
const isArchivedFilter = ref(props.filters.is_archived);

// String representations for Select components
const isActiveFilterString = ref(props.filters.is_active === null ? 'null' : props.filters.is_active === true ? 'true' : 'false');
const isArchivedFilterString = ref(props.filters.is_archived === null ? 'null' : props.filters.is_archived === true ? 'true' : 'false');

// Watch string filters and convert to boolean/null
watch(isActiveFilterString, (newValue) => {
    isActiveFilter.value = newValue === 'null' ? null : newValue === 'true';
});

watch(isArchivedFilterString, (newValue) => {
    isArchivedFilter.value = newValue === 'null' ? null : newValue === 'true';
});

// Modal states
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedSemester = ref<Semester | null>(null);

// Forms
const createForm = useForm({
    code: '',
    name: '',
    date_range: { start: null, end: null } as { start: string | null; end: string | null },
    enrollment_date_range: { start: null, end: null } as { start: string | null; end: string | null },
    is_active: false,
    is_archived: false,
});

const editForm = useForm({
    code: '',
    name: '',
    date_range: { start: null, end: null } as { start: string | null; end: string | null },
    enrollment_date_range: { start: null, end: null } as { start: string | null; end: string | null },
    is_active: false,
    is_archived: false,
});

const deleteForm = useForm({});

// Apply filters with debounce
const applyFilters = () => {
    const filters: Record<string, any> = {};

    if (search.value) filters.search = search.value;
    if (nameFilter.value) filters['filter[name]'] = nameFilter.value;
    if (yearFilter.value) filters['filter[year]'] = yearFilter.value;
    if (isActiveFilter.value !== null) filters['filter[is_active]'] = isActiveFilter.value;
    if (isArchivedFilter.value !== null) filters['filter[is_archived]'] = isArchivedFilter.value;

    router.get(route('semester.index'), filters, {
        preserveState: true,
        replace: true,
    });
};

// Watch filters for changes
let filterTimeout: ReturnType<typeof setTimeout>;
watch([search, nameFilter, yearFilter, isActiveFilter, isArchivedFilter], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 500);
});

// Extract year from semester name
const extractYear = (semester: Semester): string => {
    const nameMatch = semester.name.match(/(\d{4})/);
    if (nameMatch) return nameMatch[1];

    const startYear = new Date(semester.start_date).getFullYear();
    return startYear.toString();
};

// Table columns
const columns: ColumnDef<Semester>[] = [
    {
        accessorKey: 'code',
        header: 'Code',
        cell: ({ row }) => {
            const semester = row.original;
            return semester.code || 'N/A';
        },
    },
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => {
            const semester = row.original;
            return `${semester.name}`;
        },
    },
    {
        accessorKey: 'year',
        header: 'Year',
        cell: ({ row }) => extractYear(row.original),
    },
    {
        accessorKey: 'start_date',
        header: 'Start Date',
        cell: ({ row }) => new Date(row.original.start_date).toLocaleDateString(),
    },
    {
        accessorKey: 'end_date',
        header: 'End Date',
        cell: ({ row }) => new Date(row.original.end_date).toLocaleDateString(),
    },
    {
        accessorKey: 'enrollment_period',
        header: 'Enrollment Period',
        cell: ({ row }) => {
            const semester = row.original;
            if (!semester.enrollment_start_date || !semester.enrollment_end_date) {
                return 'Not set';
            }
            const start = new Date(semester.enrollment_start_date).toLocaleDateString();
            const end = new Date(semester.enrollment_end_date).toLocaleDateString();
            return `${start} - ${end}`;
        },
    },
    {
        accessorKey: 'status_badges',
        header: 'Status',
        cell: ({ row }) => {
            const semester = row.original;
            const badges = [];

            if (semester.is_active) {
                badges.push(h('span', { class: 'px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800' }, 'Active'));
            }

            if (semester.is_archived) {
                badges.push(h('span', { class: 'px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800' }, 'Archived'));
            }

            if (!semester.is_active && !semester.is_archived) {
                badges.push(h('span', { class: 'px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800' }, 'Inactive'));
            }

            return h('div', { class: 'flex flex-wrap gap-1' }, badges);
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const semester = row.original;
            const isArchived = semester.is_archived;

            return h('div', { class: 'flex items-center gap-2' }, [
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        disabled: isArchived,
                        onClick: () => openEditModal(semester),
                    },
                    [h(Edit, { class: 'h-4 w-4' })],
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        disabled: isArchived,
                        onClick: () => navigateToEnrollment(semester),
                    },
                    ['Manage Enrollment'],
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        disabled: isArchived,
                        onClick: () => openDeleteModal(semester),
                    },
                    [h(Trash2, { class: 'h-4 w-4' })],
                ),
            ]);
        },
    },
];

// Modal functions
const openCreateModal = () => {
    createForm.reset();
    showCreateModal.value = true;
};

// Helper function to check if semester is currently running
const isSemesterRunning = (semester: Semester): boolean => {
    const now = new Date();
    const startDate = new Date(semester.start_date);
    const endDate = new Date(semester.end_date);

    return now >= startDate && now <= endDate;
};

const openEditModal = (semester: Semester) => {
    selectedSemester.value = semester;

    // Convert datetime strings to date-only strings (YYYY-MM-DD)
    const startDate = semester.start_date ? fromDate(new Date(semester.start_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0] : null;
    const endDate = semester.end_date ? fromDate(new Date(semester.end_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0] : null;

    const enrollmentStartDate = semester.enrollment_start_date
        ? fromDate(new Date(semester.enrollment_start_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0]
        : null;
    const enrollmentEndDate = semester.enrollment_end_date
        ? fromDate(new Date(semester.enrollment_end_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0]
        : null;

    Object.assign(editForm, {
        code: semester.code,
        name: semester.name,
        date_range: {
            start: startDate,
            end: endDate,
        },
        enrollment_date_range: {
            start: enrollmentStartDate,
            end: enrollmentEndDate,
        },
        is_active: semester.is_active,
        is_archived: semester.is_archived,
    });
    showEditModal.value = true;
};

const openDeleteModal = (semester: Semester) => {
    selectedSemester.value = semester;
    showDeleteModal.value = true;
};

const closeModals = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showDeleteModal.value = false;
    selectedSemester.value = null;
};

// Form submissions
const submitCreate = () => {
    // Transform date ranges to individual date fields for backend
    const formData: any = {
        code: createForm.code,
        name: createForm.name,
        start_date: createForm.date_range.start,
        end_date: createForm.date_range.end,
        enrollment_start_date: createForm.enrollment_date_range.start,
        enrollment_end_date: createForm.enrollment_date_range.end,
        is_active: createForm.is_active,
        is_archived: createForm.is_archived,
    };

    createForm
        .transform(() => formData)
        .post(route('semester.store'), {
            onSuccess: () => {
                closeModals();
            },
        });
};

const submitEdit = () => {
    if (!selectedSemester.value) return;

    // Transform date ranges to individual date fields for backend
    const formData: any = {
        code: editForm.code,
        name: editForm.name,
        start_date: editForm.date_range.start,
        end_date: editForm.date_range.end,
        enrollment_start_date: editForm.enrollment_date_range.start,
        enrollment_end_date: editForm.enrollment_date_range.end,
        is_active: editForm.is_active,
        is_archived: editForm.is_archived,
    };

    editForm
        .transform(() => formData)
        .put(route('semester.update', selectedSemester.value!.id), {
            onSuccess: () => {
                closeModals();
            },
        });
};

const submitDelete = () => {
    if (!selectedSemester.value) return;

    deleteForm.delete(route('semester.destroy', selectedSemester.value.id), {
        onSuccess: () => {
            closeModals();
        },
    });
};

const clearFilters = () => {
    search.value = '';
    nameFilter.value = '';
    yearFilter.value = '';
    isActiveFilter.value = null;
    isArchivedFilter.value = null;
    isActiveFilterString.value = 'null';
    isArchivedFilterString.value = 'null';
};

const navigateToEnrollment = (semester: Semester) => {
    router.get(route('semesters.enrollment.show', semester.id));
};
const hasActiveFilters = computed(
    () => search.value || nameFilter.value || yearFilter.value || isActiveFilter.value !== null || isArchivedFilter.value !== null,
);

// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['semesters'],
        },
    );
};

const handlePageSizeChange = (pageSize: number) => {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', pageSize.toString());
    params.delete('page'); // Reset to first page when changing page size

    const url = `/semesters?${params.toString()}`;
    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['semesters', 'filters'],
        },
    );
};
</script>

<template>
    <Head title="Semesters" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <Heading title="Semesters" />
                <Button @click="openCreateModal" size="sm">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Semester
                    <p>{{ console.log(errors) }}</p>
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex flex-col gap-1">
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="search" placeholder="Search semesters..." />
                </div>
                <div class="flex flex-col gap-1">
                    <Label for="name-filter">Name</Label>
                    <Input id="name-filter" v-model="nameFilter" placeholder="Filter by name..." />
                </div>
                <div class="flex flex-col gap-1">
                    <Label for="year-filter">Year</Label>
                    <Input id="year-filter" v-model="yearFilter" placeholder="e.g., 2025" />
                </div>
                <div class="flex flex-col gap-1">
                    <Label for="active-filter">Active Status</Label>
                    <Select v-model="isActiveFilterString">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select active status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="null">All</SelectItem>
                            <SelectItem value="true">Active</SelectItem>
                            <SelectItem value="false">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex flex-col gap-1">
                    <Label for="archived-filter">Archived Status</Label>
                    <Select v-model="isArchivedFilterString">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select archived status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="null">All</SelectItem>
                            <SelectItem value="true">Archived</SelectItem>
                            <SelectItem value="false">Not Archived</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex h-full items-end">
                    <Button variant="outline" @click="clearFilters" :disabled="!hasActiveFilters">
                        <X class="h-4 w-4" />
                        Clear
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <DataTable :data="semesters.data" :columns="columns" :empty-message="'No semesters found.'" />

            <!-- Pagination -->
            <DataPagination
                :pagination-data="semesters"
                item-name="semesters"
                @navigate="handlePaginationNavigate"
                @page-size-change="handlePageSizeChange"
                class="mt-4"
            />

            <!-- Create Modal -->
            <Dialog v-model:open="showCreateModal">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Add New Semester</DialogTitle>
                        <DialogDescription>Create a new semester.</DialogDescription>
                    </DialogHeader>

                    <div class="grid grid-cols-2 gap-4 py-4">
                        <div>
                            <Label for="create-code">Code *</Label>
                            <Input
                                id="create-code"
                                v-model="createForm.code"
                                placeholder="e.g., SPR2025"
                                :class="{ 'border-red-500': createForm.errors.code }"
                            />
                            <p v-if="createForm.errors.code" class="mt-1 text-sm text-red-500">{{ createForm.errors.code }}</p>
                        </div>

                        <div>
                            <Label for="create-name">Name *</Label>
                            <Input
                                id="create-name"
                                v-model="createForm.name"
                                placeholder="e.g., Spring 2025"
                                :class="{ 'border-red-500': createForm.errors.name }"
                            />
                            <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-500">{{ createForm.errors.name }}</p>
                        </div>

                        <div class="col-span-2">
                            <Label for="create-date-range">Semester Date Range *</Label>
                            <DateRangePicker v-model="createForm.date_range" placeholder="Select semester date range" />
                            <p v-if="(createForm.errors as any).start_date" class="mt-1 text-sm text-red-500">
                                Start Date: {{ (createForm.errors as any).start_date }}
                            </p>
                            <p v-if="(createForm.errors as any).end_date" class="mt-1 text-sm text-red-500">
                                End Date: {{ (createForm.errors as any).end_date }}
                            </p>
                        </div>

                        <div class="col-span-2">
                            <Label for="create-enrollment-date-range">Enrollment Period (Optional)</Label>
                            <DateRangePicker v-model="createForm.enrollment_date_range" placeholder="Select enrollment period" />
                            <p v-if="(createForm.errors as any).enrollment_start_date" class="mt-1 text-sm text-red-500">
                                Enrollment Start Date: {{ (createForm.errors as any).enrollment_start_date }}
                            </p>
                            <p v-if="(createForm.errors as any).enrollment_end_date" class="mt-1 text-sm text-red-500">
                                Enrollment End Date: {{ (createForm.errors as any).enrollment_end_date }}
                            </p>
                        </div>

                        <div class="col-span-2 space-y-4">
                            <div class="flex items-center space-x-2">
                                <Switch id="create-is-active" v-model:checked="createForm.is_active" />
                                <Label for="create-is-active">Is Active (Current Semester)</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="create-is-archived" v-model:checked="createForm.is_archived" />
                                <Label for="create-is-archived">Is Archived</Label>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeModals">Cancel</Button>
                        <Button @click="submitCreate" :disabled="createForm.processing">
                            {{ createForm.processing ? 'Creating...' : 'Create Semester' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Edit Modal -->
            <Dialog v-model:open="showEditModal">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit Semester</DialogTitle>
                        <DialogDescription>Update semester information.</DialogDescription>
                    </DialogHeader>

                    <div class="grid grid-cols-2 gap-4 py-4">
                        <div>
                            <Label for="edit-code">Code</Label>
                            <Input
                                id="edit-code"
                                v-model="editForm.code"
                                placeholder="e.g., SPR2025"
                                :class="{ 'border-red-500': editForm.errors.code }"
                            />
                            <p v-if="editForm.errors.code" class="mt-1 text-sm text-red-500">{{ editForm.errors.code }}</p>
                        </div>

                        <div>
                            <Label for="edit-name">Name *</Label>
                            <Input
                                id="edit-name"
                                v-model="editForm.name"
                                placeholder="e.g., Spring 2025"
                                :class="{ 'border-red-500': editForm.errors.name }"
                            />
                            <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-500">{{ editForm.errors.name }}</p>
                        </div>

                        <div class="col-span-2">
                            <Label for="edit-date-range">Semester Date Range *</Label>
                            <DateRangePicker v-model="editForm.date_range" placeholder="Select semester date range" />
                            <p v-if="(editForm.errors as any).start_date" class="mt-1 text-sm text-red-500">
                                Start Date: {{ (editForm.errors as any).start_date }}
                            </p>
                            <p v-if="(editForm.errors as any).end_date" class="mt-1 text-sm text-red-500">
                                End Date: {{ (editForm.errors as any).end_date }}
                            </p>
                        </div>

                        <div class="col-span-2">
                            <Label for="edit-enrollment-date-range">Enrollment Period (Optional)</Label>
                            <DateRangePicker v-model="editForm.enrollment_date_range" placeholder="Select enrollment period" />
                            <p v-if="(editForm.errors as any).enrollment_start_date" class="mt-1 text-sm text-red-500">
                                Enrollment Start Date: {{ (editForm.errors as any).enrollment_start_date }}
                            </p>
                            <p v-if="(editForm.errors as any).enrollment_end_date" class="mt-1 text-sm text-red-500">
                                Enrollment End Date: {{ (editForm.errors as any).enrollment_end_date }}
                            </p>
                        </div>

                        <div class="col-span-2 space-y-4">
                            <div class="flex items-center space-x-2">
                                <Switch
                                    id="edit-is-active"
                                    v-model="editForm.is_active"
                                    :disabled="selectedSemester ? isSemesterRunning(selectedSemester) : false"
                                />
                                <div class="flex flex-col">
                                    <Label for="edit-is-active">Is Active (Current Semester)</Label>
                                    <p v-if="selectedSemester && isSemesterRunning(selectedSemester)" class="text-muted-foreground text-xs">
                                        Cannot change active status during semester period
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="edit-is-archived" v-model="editForm.is_archived" />
                                <Label for="edit-is-archived">Is Archived</Label>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeModals">Cancel</Button>
                        <Button @click="submitEdit" :disabled="editForm.processing">
                            {{ editForm.processing ? 'Updating...' : 'Update Semester' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Modal -->
            <Dialog v-model:open="showDeleteModal">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Semester</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ selectedSemester?.name }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button variant="outline" @click="closeModals">Cancel</Button>
                        <Button variant="destructive" @click="submitDelete" :disabled="deleteForm.processing">
                            {{ deleteForm.processing ? 'Deleting...' : 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
