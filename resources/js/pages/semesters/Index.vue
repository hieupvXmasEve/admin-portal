<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useApi } from '@/composables/useApiRequest';
import { PaginatedResponse } from '@/types';
import { formatDateToShort } from '@/utils/date';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { fromDate } from '@internationalized/date';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { CalendarPlus, Edit, Plus } from 'lucide-vue-next';
import { useForm as useVeeForm } from 'vee-validate';
import { h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

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

interface Campus {
    id: number;
    name: string;
    code: string;
}

interface CampusPeriodSchedule {
    id: number;
    semester_id: number;
    campus_id: number;
    campus_name: string | null;
    operating_start_date: string | null;
    operating_end_date: string | null;
    registration_start_date: string | null;
    registration_end_date: string | null;
}

interface CampusScheduleFormValues {
    campus_id: string;
    operating_date_range: {
        start: string | null;
        end: string | null;
    };
    registration_date_range: {
        start: string | null;
        end: string | null;
    };
}

interface ApiErrorItem {
    field: string | null;
    detail: string | null;
}

interface Props {
    semesters: PaginatedResponse<Semester>;
    campuses: Campus[];
    campus_period_schedules: Record<string, CampusPeriodSchedule[]>;
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
const api = useApi();

const optionalDateRangeSchema = z
    .object({
        start: z.string().nullable(),
        end: z.string().nullable(),
    })
    .refine(({ start, end }) => (start === null && end === null) || (start !== null && end !== null), 'Select both a start and end date.')
    .refine(({ start, end }) => start === null || end === null || end >= start, 'The end date must not be before the start date.');

const campusScheduleSchema = toTypedSchema(
    z.object({
        campus_id: z.string().min(1, 'Select a campus.'),
        operating_date_range: optionalDateRangeSchema,
        registration_date_range: optionalDateRangeSchema,
    }),
);

// Reactive filters
const isActiveFilter = ref(props.filters.is_active);
const isArchivedFilter = ref(props.filters.is_archived);

// String representations for Select components
const isActiveFilterString = ref(props.filters.is_active === null ? 'null' : props.filters.is_active ? 'true' : 'false');
const isArchivedFilterString = ref(props.filters.is_archived === null ? 'null' : props.filters.is_archived ? 'true' : 'false');

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
const showCampusScheduleModal = ref(false);
const selectedSemester = ref<Semester | null>(null);
const selectedCampusSchedule = ref<CampusPeriodSchedule | null>(null);

// Forms
const createForm = useForm({
    code: '',
    name: '',
    date_range: { start: null, end: null } as { start: string | null; end: string | null },
    enrollment_date_range: { start: null, end: null } as { start: string | null; end: string | null },
    is_active: false,
    is_archived: false,
});

const editFormData = ref({
    code: '',
    name: '',
    date_range: { start: null, end: null } as { start: string | null; end: string | null },
    enrollment_date_range: { start: null, end: null } as { start: string | null; end: string | null },
    is_active: false,
    is_archived: false,
});

const editFormErrors = ref<Record<string, string[]>>({});
const editFormProcessing = ref(false);

const deleteForm = useForm({});
const {
    defineField: defineCampusScheduleField,
    errors: campusScheduleErrors,
    handleSubmit: handleCampusScheduleSubmit,
    isSubmitting: isCampusScheduleSubmitting,
    resetForm: resetCampusScheduleForm,
    setFieldError: setCampusScheduleFieldError,
} = useVeeForm<CampusScheduleFormValues>({
    initialValues: {
        campus_id: '',
        operating_date_range: { start: null, end: null },
        registration_date_range: { start: null, end: null },
    },
    validationSchema: campusScheduleSchema,
});
const [campusScheduleCampusId] = defineCampusScheduleField('campus_id');
const [campusScheduleOperatingDateRange] = defineCampusScheduleField('operating_date_range');
const [campusScheduleRegistrationDateRange] = defineCampusScheduleField('registration_date_range');

const schedulesFor = (semesterId: number) => props.campus_period_schedules[String(semesterId)] ?? [];

const scheduleForCampus = (semesterId: number, campusId: number) => schedulesFor(semesterId).find((schedule) => schedule.campus_id === campusId) ?? null;

const openCampusScheduleModal = (semester: Semester, schedule: CampusPeriodSchedule | null = null, campusId: number | null = null) => {
    selectedSemester.value = semester;
    selectedCampusSchedule.value = schedule;
    resetCampusScheduleForm({
        values: {
            campus_id: schedule?.campus_id.toString() ?? campusId?.toString() ?? '',
            operating_date_range: { start: schedule?.operating_start_date ?? null, end: schedule?.operating_end_date ?? null },
            registration_date_range: { start: schedule?.registration_start_date ?? null, end: schedule?.registration_end_date ?? null },
        },
    });
    showCampusScheduleModal.value = true;
};

const submitCampusSchedule = handleCampusScheduleSubmit(async (values) => {
    if (!selectedSemester.value) return;

    const { data, error } = await api.put<CampusPeriodSchedule>(systemRoutes.semesters.upsertCampusSchedule(selectedSemester.value.id), {
        campus_id: Number(values.campus_id),
        operating_start_date: values.operating_date_range.start,
        operating_end_date: values.operating_date_range.end,
        registration_start_date: values.registration_date_range.start,
        registration_end_date: values.registration_date_range.end,
    });

    if (data.value?.success) {
        toast.success(data.value.message || 'Campus schedule saved successfully');
        showCampusScheduleModal.value = false;
        router.reload({ only: ['campus_period_schedules'] });
        return;
    }

    const apiErrors = (data.value?.errors ?? []) as unknown as ApiErrorItem[];
    for (const apiError of apiErrors) {
        if (apiError.field === 'campus_id') {
            setCampusScheduleFieldError('campus_id', apiError.detail ?? 'Invalid campus.');
        }

        if (apiError.field?.startsWith('operating_')) {
            setCampusScheduleFieldError('operating_date_range', apiError.detail ?? 'Invalid operating window.');
        }

        if (apiError.field?.startsWith('registration_')) {
            setCampusScheduleFieldError('registration_date_range', apiError.detail ?? 'Invalid registration window.');
        }
    }

    toast.error(data.value?.message || error.value || 'Failed to save campus schedule.');
});

// Apply filters with debounce
// const applyFilters = () => {
//     const filters: Record<string, any> = {};
//
//     if (search.value) filters.search = search.value;
//     if (nameFilter.value) filters['filter[name]'] = nameFilter.value;
//     if (yearFilter.value) filters['filter[year]'] = yearFilter.value;
//     if (isActiveFilter.value !== null) filters['filter[is_active]'] = isActiveFilter.value;
//     if (isArchivedFilter.value !== null) filters['filter[is_archived]'] = isArchivedFilter.value;
//     router.get(systemRoutes.semesters.index(), filters, {
//         preserveState: true,
//         replace: true,
//     });
// };

// Watch filters for changes
// let filterTimeout: ReturnType<typeof setTimeout>;
// watch([search, nameFilter, yearFilter, isActiveFilter, isArchivedFilter], () => {
//     clearTimeout(filterTimeout);
//     filterTimeout = setTimeout(applyFilters, 500);
// });

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
        cell: ({ row }) => formatDateToShort(row.original.start_date), // convert to dd MMM yyyy
    },
    {
        accessorKey: 'end_date',
        header: 'End Date',
        cell: ({ row }) => formatDateToShort(row.original.end_date),
    },
    // {
    //     accessorKey: 'enrollment_period',
    //     header: 'Enrollment Period',
    //     cell: ({ row }) => {
    //         const semester = row.original;
    //         if (!semester.enrollment_start_date || !semester.enrollment_end_date) {
    //             return 'Not set';
    //         }
    //         const start = formatDateToShort(semester.enrollment_start_date);
    //         const end = formatDateToShort(semester.enrollment_end_date);
    //         return `${start} - ${end}`;
    //     },
    // },
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
            const disable = semester.is_active;

            return h('div', { class: 'flex items-center gap-2 w-20' }, [
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        disabled: disable,
                        onClick: () => openEditModal(semester),
                    },
                    () => [h(Edit, { class: 'h-4 w-4' })],
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        disabled: semester.is_archived,
                        onClick: () => navigateToEnrollment(semester),
                    },
                    () => ['Manage Enrollment'],
                ),
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        onClick: () => openCampusScheduleModal(semester),
                    },
                    () => [h(CalendarPlus, { class: 'h-4 w-4' })],
                ),
                // h(
                //     Button,
                //     {
                //         size: 'sm',
                //         variant: 'outline',
                //         disabled: isArchived,
                //         onClick: () => openDeleteModal(semester),
                //     },
                //     () => [h(Trash2, { class: 'h-4 w-4' })],
                // ),
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

    const enrollmentStartDate = semester.enrollment_start_date ? fromDate(new Date(semester.enrollment_start_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0] : null;
    const enrollmentEndDate = semester.enrollment_end_date ? fromDate(new Date(semester.enrollment_end_date), 'Asia/Ho_Chi_Minh').toString().split('T')[0] : null;

    Object.assign(editFormData.value, {
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
    editFormErrors.value = {};
    showEditModal.value = true;
};

// const openDeleteModal = (semester: Semester) => {
//     selectedSemester.value = semester;
//     showDeleteModal.value = true;
// };

const closeModals = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showDeleteModal.value = false;
    selectedSemester.value = null;
};

// Form submissions
const submitCreate = () => {
    // Transform date ranges to individual date fields for backend
    const formData = {
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
        .post(systemRoutes.semesters.store(), {
            onSuccess: () => {
                closeModals();
            },
        });
};

const submitEdit = async () => {
    if (!selectedSemester.value) return;

    editFormProcessing.value = true;
    editFormErrors.value = {};

    try {
        // Transform date ranges to individual date fields for backend
        const formData = {
            code: editFormData.value.code,
            name: editFormData.value.name,
            start_date: editFormData.value.date_range.start,
            end_date: editFormData.value.date_range.end,
            enrollment_start_date: editFormData.value.enrollment_date_range.start,
            enrollment_end_date: editFormData.value.enrollment_date_range.end,
            is_active: editFormData.value.is_active,
            is_archived: editFormData.value.is_archived,
        };

        const response = await fetch(systemRoutes.semesters.apiUpdate(selectedSemester.value.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(formData),
        });

        const result = await response.json();

        if (result.success) {
            toast.success(result.message);
            closeModals();
            // Refresh the page data
            router.reload({ only: ['semesters'] });
        } else {
            if (result.errors) {
                editFormErrors.value = result.errors;
            }
            toast.error(result.message || 'Failed to update semester');
        }
    } catch (error) {
        console.error('Error updating semester:', error);
        toast.error('An unexpected error occurred');
    } finally {
        editFormProcessing.value = false;
    }
};

const submitDelete = () => {
    if (!selectedSemester.value) return;

    deleteForm.delete(systemRoutes.semesters.destroy(selectedSemester.value.id), {
        onSuccess: () => {
            toast.success('Semester deleted successfully');
            closeModals();
        },
    });
};

const navigateToEnrollment = (semester: Semester) => {
    router.get(systemRoutes.semesters.enrollment(semester.id));
};

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

    <div class="flex items-center justify-between">
        <Heading title="Semesters" />
        <Button @click="openCreateModal" size="sm">
            <Plus class="mr-2 h-4 w-4" />
            Add Semester
        </Button>
    </div>

    <!-- Filters -->
    <!--    <div class="flex flex-wrap items-center gap-4">-->
    <!--        <div class="flex flex-col gap-1">-->
    <!--            <Label for="search">Search</Label>-->
    <!--            <Input id="search" v-model="search" placeholder="Search semesters..." />-->
    <!--        </div>-->
    <!--        <div class="flex flex-col gap-1">-->
    <!--            <Label for="name-filter">Name</Label>-->
    <!--            <Input id="name-filter" v-model="nameFilter" placeholder="Filter by name..." />-->
    <!--        </div>-->
    <!--        <div class="flex flex-col gap-1">-->
    <!--            <Label for="year-filter">Year</Label>-->
    <!--            <Input id="year-filter" v-model="yearFilter" placeholder="e.g., 2025" />-->
    <!--        </div>-->
    <!--        <div class="flex flex-col gap-1">-->
    <!--            <Label for="active-filter">Active Status</Label>-->
    <!--            <Select v-model="isActiveFilterString">-->
    <!--                <SelectTrigger class="w-full">-->
    <!--                    <SelectValue placeholder="Select active status" />-->
    <!--                </SelectTrigger>-->
    <!--                <SelectContent>-->
    <!--                    <SelectItem value="null">All</SelectItem>-->
    <!--                    <SelectItem value="true">Active</SelectItem>-->
    <!--                    <SelectItem value="false">Inactive</SelectItem>-->
    <!--                </SelectContent>-->
    <!--            </Select>-->
    <!--        </div>-->
    <!--        <div class="flex flex-col gap-1">-->
    <!--            <Label for="archived-filter">Archived Status</Label>-->
    <!--            <Select v-model="isArchivedFilterString">-->
    <!--                <SelectTrigger class="w-full">-->
    <!--                    <SelectValue placeholder="Select archived status" />-->
    <!--                </SelectTrigger>-->
    <!--                <SelectContent>-->
    <!--                    <SelectItem value="null">All</SelectItem>-->
    <!--                    <SelectItem value="true">Archived</SelectItem>-->
    <!--                    <SelectItem value="false">Not Archived</SelectItem>-->
    <!--                </SelectContent>-->
    <!--            </Select>-->
    <!--        </div>-->
    <!--        <div class="flex flex-col gap-1">-->
    <!--            <Label class="text-xs text-transparent">Clear</Label>-->
    <!--            <Button variant="outline" @click="clearFilters" :disabled="!hasActiveFilters">-->
    <!--                <X class="h-4 w-4" />-->
    <!--                Clear-->
    <!--            </Button>-->
    <!--        </div>-->
    <!--    </div>-->

    <!-- Table -->
    <DataTable :data="semesters.data" :columns="columns" :empty-message="'No semesters found.'" />

    <!-- Pagination -->
    <DataPagination :pagination-data="semesters" item-name="semesters" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" class="mt-4" />

    <section class="mt-8 space-y-3">
        <div>
            <h2 class="text-base font-semibold">Campus schedules</h2>
            <p class="text-muted-foreground text-sm">Campus-specific operating and registration windows; Academic Period remains institution-wide.</p>
        </div>
        <div v-for="semester in semesters.data" :key="`schedules-${semester.id}`" class="rounded-lg border p-4">
            <div class="mb-3">
                <div>
                    <p class="font-medium">{{ semester.name }}</p>
                    <p class="text-muted-foreground text-sm">{{ semester.code }}</p>
                </div>
            </div>
            <div v-if="campuses.length" class="grid gap-2 md:grid-cols-2">
                <div v-for="campus in campuses" :key="campus.id" class="bg-muted/50 rounded-md p-3 text-sm">
                    <template v-if="scheduleForCampus(semester.id, campus.id)">
                        <div v-for="schedule in [scheduleForCampus(semester.id, campus.id)]" :key="schedule.id" class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ campus.name }}</p>
                                <p class="text-muted-foreground">Operating: {{ schedule.operating_start_date ? `${formatDateToShort(schedule.operating_start_date)} – ${formatDateToShort(schedule.operating_end_date!)}` : 'Not set' }}</p>
                                <p class="text-muted-foreground">Registration: {{ schedule.registration_start_date ? `${formatDateToShort(schedule.registration_start_date)} – ${formatDateToShort(schedule.registration_end_date!)}` : 'Not set' }}</p>
                            </div>
                            <Button size="sm" variant="ghost" @click="openCampusScheduleModal(semester, schedule)">Edit</Button>
                        </div>
                    </template>
                    <div v-else class="flex items-center justify-between gap-2">
                        <div>
                            <p class="font-medium">{{ campus.name }}</p>
                            <p class="text-muted-foreground">No schedule configured.</p>
                        </div>
                        <Button size="sm" variant="outline" @click="openCampusScheduleModal(semester, null, campus.id)">
                            <CalendarPlus class="mr-2 h-4 w-4" />
                            Add schedule
                        </Button>
                    </div>
                </div>
            </div>
            <p v-else class="text-muted-foreground text-sm">No campuses are available to configure.</p>
        </div>
    </section>

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
                    <Input id="create-code" v-model="createForm.code" placeholder="e.g., SPR2025" :class="{ 'border-red-500': createForm.errors.code }" />
                    <p v-if="createForm.errors.code" class="mt-1 text-sm text-red-500">{{ createForm.errors.code }}</p>
                </div>

                <div>
                    <Label for="create-name">Name *</Label>
                    <Input id="create-name" v-model="createForm.name" placeholder="e.g., Spring 2025" :class="{ 'border-red-500': createForm.errors.name }" />
                    <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-500">{{ createForm.errors.name }}</p>
                </div>

                <div class="col-span-2">
                    <Label for="create-date-range">Semester Date Range *</Label>
                    <DateRangePicker v-model="createForm.date_range" placeholder="Select semester date range" />
                    <p v-if="(createForm.errors as any).start_date" class="mt-1 text-sm text-red-500">Start Date: {{ (createForm.errors as any).start_date }}</p>
                    <p v-if="(createForm.errors as any).end_date" class="mt-1 text-sm text-red-500">End Date: {{ (createForm.errors as any).end_date }}</p>
                </div>

                <div class="col-span-2">
                    <Label for="create-enrollment-date-range">Enrollment Period (Optional)</Label>
                    <DateRangePicker v-model="createForm.enrollment_date_range" placeholder="Select enrollment period" />
                    <p v-if="(createForm.errors as any).enrollment_start_date" class="mt-1 text-sm text-red-500">Enrollment Start Date: {{ (createForm.errors as any).enrollment_start_date }}</p>
                    <p v-if="(createForm.errors as any).enrollment_end_date" class="mt-1 text-sm text-red-500">Enrollment End Date: {{ (createForm.errors as any).enrollment_end_date }}</p>
                </div>

                <div class="col-span-2 space-y-4">
                    <div class="flex items-center space-x-2">
                        <Switch id="create-is-active" v-model="createForm.is_active" />
                        <Label for="create-is-active">Is Active (Current Semester)</Label>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Switch id="create-is-archived" v-model="createForm.is_archived" />
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

    <Dialog v-model:open="showCampusScheduleModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ selectedCampusSchedule ? 'Edit campus schedule' : 'Add campus schedule' }}</DialogTitle>
                <DialogDescription>Configure campus-specific windows for {{ selectedSemester?.name }} without creating a separate Academic Period.</DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div>
                    <Label for="campus-schedule-campus">Campus *</Label>
                    <select id="campus-schedule-campus" v-model="campusScheduleCampusId" class="border-input bg-background mt-1 flex h-9 w-full rounded-md border px-3 text-sm" :disabled="selectedCampusSchedule !== null">
                        <option value="" disabled>Select a campus</option>
                        <option v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">{{ campus.name }} ({{ campus.code }})</option>
                    </select>
                    <p v-if="campusScheduleErrors.campus_id" class="text-destructive mt-1 text-sm">{{ campusScheduleErrors.campus_id }}</p>
                </div>
                <div>
                    <Label>Operating window</Label>
                    <DateRangePicker v-model="campusScheduleOperatingDateRange" placeholder="Select operating dates" />
                    <p v-if="campusScheduleErrors.operating_date_range" class="text-destructive mt-1 text-sm">{{ campusScheduleErrors.operating_date_range }}</p>
                </div>
                <div>
                    <Label>Registration window</Label>
                    <DateRangePicker v-model="campusScheduleRegistrationDateRange" placeholder="Select registration dates" />
                    <p v-if="campusScheduleErrors.registration_date_range" class="text-destructive mt-1 text-sm">{{ campusScheduleErrors.registration_date_range }}</p>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showCampusScheduleModal = false">Cancel</Button>
                <Button :disabled="isCampusScheduleSubmitting" @click="submitCampusSchedule">{{ isCampusScheduleSubmitting ? 'Saving...' : 'Save schedule' }}</Button>
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
                    <Input id="edit-code" v-model="editFormData.code" placeholder="e.g., SPR2025" :class="{ 'border-red-500': editFormErrors.code }" />
                    <p v-if="editFormErrors.code" class="mt-1 text-sm text-red-500">{{ editFormErrors.code[0] }}</p>
                </div>

                <div>
                    <Label for="edit-name">Name *</Label>
                    <Input id="edit-name" v-model="editFormData.name" placeholder="e.g., Spring 2025" :class="{ 'border-red-500': editFormErrors.name }" />
                    <p v-if="editFormErrors.name" class="mt-1 text-sm text-red-500">{{ editFormErrors.name[0] }}</p>
                </div>

                <div class="col-span-2">
                    <Label for="edit-date-range">Semester Date Range *</Label>
                    <DateRangePicker v-model="editFormData.date_range" placeholder="Select semester date range" />
                    <p v-if="editFormErrors.start_date" class="mt-1 text-sm text-red-500">Start Date: {{ editFormErrors.start_date[0] }}</p>
                    <p v-if="editFormErrors.end_date" class="mt-1 text-sm text-red-500">End Date: {{ editFormErrors.end_date[0] }}</p>
                </div>

                <div class="col-span-2">
                    <Label for="edit-enrollment-date-range">Enrollment Period (Optional)</Label>
                    <DateRangePicker v-model="editFormData.enrollment_date_range" placeholder="Select enrollment period" />
                    <p v-if="editFormErrors.enrollment_start_date" class="mt-1 text-sm text-red-500">Enrollment Start Date: {{ editFormErrors.enrollment_start_date[0] }}</p>
                    <p v-if="editFormErrors.enrollment_end_date" class="mt-1 text-sm text-red-500">Enrollment End Date: {{ editFormErrors.enrollment_end_date[0] }}</p>
                </div>

                <div class="col-span-2 space-y-4">
                    <div class="flex items-center space-x-2">
                        <Switch id="edit-is-active" v-model="editFormData.is_active" :disabled="selectedSemester ? isSemesterRunning(selectedSemester) : false" />
                        <div class="flex flex-col">
                            <Label for="edit-is-active">Is Active (Current Semester)</Label>
                            <p v-if="selectedSemester && isSemesterRunning(selectedSemester)" class="text-muted-foreground text-xs">Cannot change active status during semester period</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Switch id="edit-is-archived" v-model="editFormData.is_archived" />
                        <Label for="edit-is-archived">Is Archived</Label>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeModals">Cancel</Button>
                <Button @click="submitEdit" :disabled="editFormProcessing">
                    {{ editFormProcessing ? 'Updating...' : 'Update Semester' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Delete Modal -->
    <Dialog v-model:open="showDeleteModal">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Semester</DialogTitle>
                <DialogDescription> Are you sure you want to delete "{{ selectedSemester?.name }}"? This action cannot be undone. </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button variant="outline" @click="closeModals">Cancel</Button>
                <Button variant="destructive" @click="submitDelete" :disabled="deleteForm.processing">
                    {{ deleteForm.processing ? 'Deleting...' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
