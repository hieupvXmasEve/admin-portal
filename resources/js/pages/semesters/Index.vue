<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import {
    DropdownMenu as Select,
    DropdownMenuContent as SelectContent,
    DropdownMenuItem as SelectItem,
    DropdownMenuTrigger as SelectTrigger
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { fromDate } from '@internationalized/date';
import type { ColumnDef } from '@tanstack/vue-table';
import { ChevronDown, Edit, Lock, Plus, Trash2, Unlock } from 'lucide-vue-next';
import { h, ref, watch } from 'vue';

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    locked_status: 'locked' | 'unlocked';
    is_attendance_locked: boolean;
    is_certificate_locked: boolean;
    has_tuition_fee: boolean;
    has_gc_fee: boolean;
    campus: {
        id: number;
        name: string;
    };
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
        locked_status: string | null;
    };
}

const props = defineProps<Props>();
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'List Semesters',
        href: '/semesters'
    }
];
// Reactive filters
const search = ref(props.filters.search || '');
const nameFilter = ref(props.filters.name || '');
const yearFilter = ref(props.filters.year || '');
const lockedStatusFilter = ref(props.filters.locked_status || '');

// Modal states
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedSemester = ref<Semester | null>(null);

// Forms
const createForm = useForm({
    name: '',
    date_range: { start: null, end: null } as { start: string | null; end: string | null },
    locked_status: 'unlocked' as 'locked' | 'unlocked',
    is_attendance_locked: false,
    is_certificate_locked: false,
    has_tuition_fee: false,
    has_gc_fee: false
});

const editForm = useForm({
    name: '',
    date_range: { start: null, end: null } as { start: string | null; end: string | null },
    locked_status: 'unlocked' as 'locked' | 'unlocked',
    is_attendance_locked: false,
    is_certificate_locked: false,
    has_tuition_fee: false,
    has_gc_fee: false
});

const deleteForm = useForm({});

// Apply filters with debounce
const applyFilters = () => {
    const filters: Record<string, any> = {};

    if (search.value) filters.search = search.value;
    if (nameFilter.value) filters['filter[name]'] = nameFilter.value;
    if (yearFilter.value) filters['filter[year]'] = yearFilter.value;
    if (lockedStatusFilter.value) filters['filter[locked_status]'] = lockedStatusFilter.value;

    router.get(route('semester.index'), filters, {
        preserveState: true,
        replace: true
    });
};

// Watch filters for changes
let filterTimeout: ReturnType<typeof setTimeout>;
watch([search, nameFilter, yearFilter, lockedStatusFilter], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 500);
});

// Extract year from semester name or dates
const extractYear = (semester: Semester): string => {
    const nameMatch = semester.name.match(/(\d{4})/);
    if (nameMatch) return nameMatch[1];

    const startYear = new Date(semester.start_date).getFullYear();
    return startYear.toString();
};

// Table columns
const columns: ColumnDef<Semester>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => {
            const semester = row.original;
            return `${semester.name}`;
        }
    },

    {
        accessorKey: 'year',
        header: 'Year',
        cell: ({ row }) => extractYear(row.original)
    },
    {
        accessorKey: 'start_date',
        header: 'Start Date',
        cell: ({ row }) => new Date(row.original.start_date).toLocaleDateString()
    },
    {
        accessorKey: 'end_date',
        header: 'End Date',
        cell: ({ row }) => new Date(row.original.end_date).toLocaleDateString()
    },
    {
        accessorKey: 'locked_status',
        header: 'Status',
        cell: ({ row }) => {
            const isLocked = row.original.locked_status === 'locked';
            return h('div', { class: 'flex items-center gap-2' }, [
                h(isLocked ? Lock : Unlock, { class: `h-4 w-4 ${isLocked ? 'text-red-500' : 'text-green-500'}` }),
                h(
                    'span',
                    {
                        class: `px-2 py-1 rounded text-xs font-medium capitalize ${isLocked ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}`
                    },
                    isLocked ? 'Locked' : 'Unlocked'
                )
            ]);
        }
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const semester = row.original;
            const isLocked = semester.locked_status === 'locked';

            return h('div', { class: 'flex items-center gap-2' }, [
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        disabled: isLocked,
                        onClick: () => openEditModal(semester)
                    },
                    [h(Edit, { class: 'h-4 w-4' })]
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        disabled: isLocked,
                        onClick: () => openDeleteModal(semester)
                    },
                    [h(Trash2, { class: 'h-4 w-4' })]
                )
            ]);
        }
    }
];

// Modal functions
const openCreateModal = () => {
    createForm.reset();
    showCreateModal.value = true;
};

const openEditModal = (semester: Semester) => {
    selectedSemester.value = semester;
    // Convert datetime strings to date-only strings (YYYY-MM-DD)
    const startDate = semester.start_date ? fromDate(new Date(semester.start_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0] : null;
    const endDate = semester.end_date ? fromDate(new Date(semester.end_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0] : null;

    Object.assign(editForm, {
        name: semester.name,
        date_range: {
            start: startDate,
            end: endDate
        },
        locked_status: semester.locked_status,
        is_attendance_locked: semester.is_attendance_locked,
        is_certificate_locked: semester.is_certificate_locked,
        has_tuition_fee: semester.has_tuition_fee,
        has_gc_fee: semester.has_gc_fee
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
    // Transform date_range to start_date and end_date for backend
    const formData: any = {
        ...createForm.data(),
        start_date: createForm.date_range.start,
        end_date: createForm.date_range.end
    };
    delete formData.date_range;

    createForm
        .transform(() => formData)
        .post(route('semester.store'), {
            onSuccess: () => {
                closeModals();
            }
        });
};

const submitEdit = () => {
    if (!selectedSemester.value) return;

    // Transform date_range to start_date and end_date for backend
    const formData: any = {
        ...editForm.data(),
        start_date: editForm.date_range.start,
        end_date: editForm.date_range.end
    };
    delete formData.date_range;

    editForm
        .transform(() => formData)
        .put(route('semester.update', selectedSemester.value!.id), {
            onSuccess: () => {
                closeModals();
            }
        });
};

const submitDelete = () => {
    if (!selectedSemester.value) return;

    deleteForm.delete(route('semester.destroy', selectedSemester.value.id), {
        onSuccess: () => {
            closeModals();
        }
    });
};

const clearFilters = () => {
    search.value = '';
    nameFilter.value = '';
    yearFilter.value = '';
    lockedStatusFilter.value = '';
};

// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['semesters']
    });
};

const handlePageSizeChange = (pageSize: number) => {
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', pageSize.toString());
    params.delete('page'); // Reset to first page when changing page size

    const url = `/semesters?${params.toString()}`;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['semesters', 'filters']
    });
};
</script>

<template>
    <Head title="Semesters" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <Heading title="Semesters" />
                <Button @click="openCreateModal">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Semester
                </Button>
            </div>

            <!-- Filters -->
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-5">
                <div>
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="search" placeholder="Search semesters..." class="mt-1" />
                </div>
                <div>
                    <Label for="name-filter">Name</Label>
                    <Input id="name-filter" v-model="nameFilter" placeholder="Filter by name..." class="mt-1" />
                </div>
                <div>
                    <Label for="year-filter">Year</Label>
                    <Input id="year-filter" v-model="yearFilter" placeholder="e.g., 2025" class="mt-1" />
                </div>
                <div>
                    <Label for="status-filter">Status</Label>
                    <Select>
                        <SelectTrigger as-child>
                            <Button variant="outline" class="mt-1 w-full justify-between">
                                {{ lockedStatusFilter || 'All statuses' }}
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem @click="lockedStatusFilter = ''">All statuses</SelectItem>
                            <SelectItem @click="lockedStatusFilter = 'unlocked'">Unlocked</SelectItem>
                            <SelectItem @click="lockedStatusFilter = 'locked'">Locked</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex items-end">
                    <Button variant="outline" @click="clearFilters" class="mt-1"> Clear Filters</Button>
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
                        <DialogDescription>Create a new semester for the current campus.</DialogDescription>
                    </DialogHeader>

                    <div class="grid grid-cols-2 gap-4 py-4">
                        <div class="col-span-2">
                            <Label for="create-name">Name *</Label>
                            <Input
                                id="create-name"
                                v-model="createForm.name"
                                placeholder="e.g., SUM2025"
                                :class="{ 'border-red-500': createForm.errors.name }"
                            />
                            <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-500">{{ createForm.errors.name
                                }}</p>
                        </div>

                        <div>
                            <Label for="create-locked-status">Status *</Label>
                            <Select>
                                <SelectTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-between', { 'border-red-500': createForm.errors.locked_status }]"
                                    >
                                        {{ createForm.locked_status === 'locked' ? 'Locked' : 'Unlocked' }}
                                        <ChevronDown class="h-4 w-4" />
                                    </Button>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem @click="createForm.locked_status = 'unlocked'">Unlocked</SelectItem>
                                    <SelectItem @click="createForm.locked_status = 'locked'">Locked</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="createForm.errors.locked_status" class="mt-1 text-sm text-red-500">
                                {{ createForm.errors.locked_status }}
                            </p>
                        </div>

                        <div class="col-span-2">
                            <Label for="create-date-range">Date Range *</Label>
                            <DateRangePicker v-model="createForm.date_range" placeholder="Select semester date range" />
                            <p v-if="(createForm.errors as any).start_date" class="mt-1 text-sm text-red-500">
                                Start Date: {{ (createForm.errors as any).start_date }}
                            </p>
                            <p v-if="(createForm.errors as any).end_date" class="mt-1 text-sm text-red-500">
                                End Date: {{ (createForm.errors as any).end_date }}
                            </p>
                        </div>

                        <div class="col-span-2 space-y-4">
                            <div class="flex items-center space-x-2">
                                <Switch id="create-attendance-locked"
                                        v-model:checked="createForm.is_attendance_locked" />
                                <Label for="create-attendance-locked">Attendance Locked</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="create-certificate-locked"
                                        v-model:checked="createForm.is_certificate_locked" />
                                <Label for="create-certificate-locked">Certificate Locked</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="create-tuition-fee" v-model:checked="createForm.has_tuition_fee" />
                                <Label for="create-tuition-fee">Has Tuition Fee</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="create-gc-fee" v-model:checked="createForm.has_gc_fee" />
                                <Label for="create-gc-fee">Has GC Fee</Label>
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
                        <div class="col-span-2">
                            <Label for="edit-name">Name *</Label>
                            <Input
                                id="edit-name"
                                v-model="editForm.name"
                                placeholder="e.g., SUM2025"
                                :class="{ 'border-red-500': editForm.errors.name }"
                            />
                            <p v-if="editForm.errors.name" class="mt-1 text-sm text-red-500">{{ editForm.errors.name
                                }}</p>
                        </div>

                        <div>
                            <Label for="edit-locked-status">Status *</Label>
                            <Select>
                                <SelectTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-between', { 'border-red-500': editForm.errors.locked_status }]"
                                    >
                                        {{ editForm.locked_status === 'locked' ? 'Locked' : 'Unlocked' }}
                                        <ChevronDown class="h-4 w-4" />
                                    </Button>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem @click="editForm.locked_status = 'unlocked'">Unlocked</SelectItem>
                                    <SelectItem @click="editForm.locked_status = 'locked'">Locked</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="editForm.errors.locked_status" class="mt-1 text-sm text-red-500">
                                {{ editForm.errors.locked_status }}
                            </p>
                        </div>

                        <div class="col-span-2">
                            <Label for="edit-date-range">Date Range *</Label>
                            <DateRangePicker v-model="editForm.date_range" placeholder="Select semester date range" />
                            <p v-if="(editForm.errors as any).start_date" class="mt-1 text-sm text-red-500">
                                Start Date: {{ (editForm.errors as any).start_date }}
                            </p>
                            <p v-if="(editForm.errors as any).end_date" class="mt-1 text-sm text-red-500">
                                End Date: {{ (editForm.errors as any).end_date }}
                            </p>
                        </div>

                        <div class="col-span-2 space-y-4">
                            <div class="flex items-center space-x-2">
                                <Switch id="edit-attendance-locked" v-model="editForm.is_attendance_locked" />
                                <Label for="edit-attendance-locked">Attendance Locked</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="edit-certificate-locked" v-model="editForm.is_certificate_locked" />
                                <Label for="edit-certificate-locked">Certificate Locked</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="edit-tuition-fee" v-model="editForm.has_tuition_fee" />
                                <Label for="edit-tuition-fee">Has Tuition Fee</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="edit-gc-fee" v-model="editForm.has_gc_fee" />
                                <Label for="edit-gc-fee">Has GC Fee</Label>
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
                            Are you sure you want to delete "{{ selectedSemester?.name }}"? This action cannot be
                            undone.
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
