<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { BookOpen, CheckCircle, Clock, Eye, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';

// UI Components
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

// Data Table Components
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';

// Types
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { CourseRegistrationRecord, RegistrationsData, RegistrationsFilters } from '@/types/models';

interface Props {
    registrations: RegistrationsData;
    studentId: number;
    filters?: Partial<RegistrationsFilters>;
}

const props = defineProps<Props>();

// Reactive state
const loading = ref(false);
const isDetailsModalOpen = ref(false);
const selectedRegistration = ref<CourseRegistrationRecord | null>(null);

// Filters using useInertiaFilters
const { filters, hasActiveFilters, clearFilters, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useInertiaFilters<RegistrationsFilters>({
    baseUrl: `/students/${props.studentId}/academic-summary/registrations`,
    initialFilters: {
        academic_year: (typeof props.filters?.academic_year === 'string' ? props.filters.academic_year : '') || '',
        semester_id: (typeof props.filters?.semester_id === 'number' ? props.filters.semester_id : typeof props.filters?.semester_id === 'string' ? parseInt(props.filters.semester_id) : 0) || 0,
        status: (typeof props.filters?.status === 'string' ? props.filters.status : 'all') || 'all',
        is_retake: (typeof props.filters?.is_retake === 'string' ? props.filters.is_retake : 'all') || 'all',
        per_page: props.filters?.per_page || 50,
        page: props.registrations.pagination.current_page || 1,
        sort: (typeof props.filters?.sort === 'string' ? props.filters.sort : 'registration_date') || 'registration_date',
        direction: (props.filters?.direction as 'asc' | 'desc') || 'desc',
    },
    defaultValues: {
        academic_year: '',
        semester_id: 0,
        status: 'all',
        is_retake: 'all',
        per_page: 50,
        sort: 'registration_date',
        direction: 'desc',
    },
    only: ['registrations', 'filters'],
    debounce: 400,
});

const paginationData = computed(() => {
    const pagination = props.registrations.pagination;
    return {
        current_page: pagination.current_page,
        last_page: pagination.last_page,
        per_page: pagination.per_page,
        total: pagination.total,
        from: pagination.from,
        to: pagination.to,
        prev_page_url: null,
        next_page_url: null,
        links: [],
        first_page_url: '',
        last_page_url: '',
        path: '',
    };
});

// Column definitions for DataTable
const columns: ColumnDef<CourseRegistrationRecord>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.registrations.pagination.current_page;
            const perPage = props.registrations.pagination.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Course',
        id: 'course',
        accessorKey: 'course_name',
        enableSorting: true,
        cell: 'course',
    },
    {
        header: 'Semester',
        id: 'semester',
        accessorKey: 'semester',
        enableSorting: true,
        cell: 'semester',
    },
    {
        header: 'Registration Status',
        id: 'registration_status',
        accessorKey: 'registration_status',
        enableSorting: false,
        cell: 'registration_status',
    },
    {
        header: 'Status',
        id: 'completion_status',
        accessorKey: 'completion_status',
        enableSorting: true,
        cell: 'status',
    },
    {
        header: 'Grade',
        id: 'grade_status',
        accessorKey: 'grade_status',
        enableSorting: true,
        cell: 'grade',
    },
    {
        header: 'Pass/Fail',
        id: 'pass_fail_status',
        accessorKey: 'pass_fail_status',
        enableSorting: true,
        cell: 'pass_fail',
    },
    {
        header: 'Credits',
        accessorKey: 'credit_points',
        enableSorting: true,
        cell: ({ row }) => `${row.original.credit_points ?? 0} pts`,
    },
    {
        header: 'Retake',
        id: 'retake',
        enableSorting: false,
        cell: 'retake',
    },
    {
        header: 'Registration Date',
        accessorKey: 'registration_date',
        id: 'registration_date',
        enableSorting: true,
        cell: ({ row }) => row.original.formatted_registration_date || '-',
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions',
    },
];

// Methods
const formatStatusName = (status: string | null): string => {
    if (!status) return 'N/A';
    return status.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatDate = (dateString: string): string => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const showDetails = (registration: CourseRegistrationRecord): void => {
    selectedRegistration.value = registration;
    isDetailsModalOpen.value = true;
};

onMounted(() => {
    // Component mounted
});
</script>

<template>
    <div class="space-y-6">
        <!-- Summary Dashboard -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Registrations</p>
                        <p class="text-2xl font-bold text-gray-900">{{ registrations.summary.total_registrations }}</p>
                    </div>
                    <div class="rounded-full bg-blue-50 p-3">
                        <BookOpen class="h-6 w-6 text-blue-600" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <p class="text-2xl font-bold text-green-600">{{ registrations.summary.completed }}</p>
                        <p class="text-xs text-gray-500">{{ registrations.summary.completion_rate }}% completion rate</p>
                    </div>
                    <div class="rounded-full bg-green-50 p-3">
                        <CheckCircle class="h-6 w-6 text-green-600" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Active</p>
                        <p class="text-2xl font-bold text-blue-600">{{ registrations.summary.active }}</p>
                        <p class="text-xs text-gray-500">Current enrollments</p>
                    </div>
                    <div class="rounded-full bg-blue-50 p-3">
                        <Clock class="h-6 w-6 text-blue-600" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-[200px] flex-1">
                    <Label for="academic-year-filter">Academic Year</Label>
                    <Select :model-value="String(filters.academic_year ?? 'all')" @update:model-value="(val) => handleSelectFilter('academic_year', val as string)">
                        <SelectTrigger id="academic-year-filter">
                            <SelectValue placeholder="All Academic Years" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Academic Years</SelectItem>
                            <SelectItem v-for="group in registrations.semester_groups" :key="group.academic_year" :value="group.academic_year"> {{ group.academic_year }} ({{ group.total_registrations }} courses) </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[200px] flex-1">
                    <Label for="semester-filter">Semester</Label>
                    <Select :model-value="String(filters.semester_id ?? 0)" @update:model-value="(val) => handleSelectFilter('semester_id', parseInt(val as string))">
                        <SelectTrigger id="semester-filter">
                            <SelectValue placeholder="All Semesters" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0">All Semesters</SelectItem>
                            <template v-for="group in registrations.semester_groups" :key="group.academic_year">
                                <SelectItem v-for="semester in group.semesters" :key="semester.id" :value="String(semester.id)"> {{ semester.name }} ({{ semester.academic_year }}) </SelectItem>
                            </template>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[200px] flex-1">
                    <Label for="status-filter">Status</Label>
                    <Select :model-value="String(filters.status ?? 'all')" @update:model-value="(val) => handleSelectFilter('status', val as string)">
                        <SelectTrigger id="status-filter">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem v-for="status in registrations.status_breakdown" :key="status.status" :value="status.status"> {{ formatStatusName(status.status) }} ({{ status.count }}) </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[150px] flex-1">
                    <Label for="retake-filter">Retakes</Label>
                    <Select :model-value="String(filters.is_retake ?? 'all')" @update:model-value="(val) => handleSelectFilter('is_retake', val as string)">
                        <SelectTrigger id="retake-filter">
                            <SelectValue placeholder="All Courses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Courses</SelectItem>
                            <SelectItem value="true">Retakes Only</SelectItem>
                            <SelectItem value="false">First Attempts Only</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                    <X class="mr-2 h-4 w-4" />
                    Clear Filters
                </Button>
            </div>
        </div>

        <!-- Desktop Table View -->
        <div class="hidden rounded-lg border bg-white shadow-sm md:block">
            <DataTable :data="registrations.data" :columns="columns" :loading="loading" :initial-sort="currentSort" :initial-direction="currentDirection" enable-server-sorting @sort-change="handleSortChange">
                <template #cell-course="{ row }">
                    <div>
                        <Link :href="route('course-offerings.show', row.original.course_offering_id)" class="font-medium text-blue-600 hover:underline">
                            {{ row.original.course_name }}
                        </Link>
                        <p class="text-sm text-gray-500">
                            {{ row.original.course_code }}
                            <span v-if="row.original.section_code && row.original.section_code !== 'N/A'"> — Section {{ row.original.section_code }}</span>
                        </p>
                    </div>
                </template>

                <template #cell-semester="{ row }">
                    <div>
                        <p class="font-medium text-gray-900">{{ row.original.semester }}</p>
                        <p class="text-sm text-gray-500">{{ row.original.academic_year }}</p>
                    </div>
                </template>

                <template #cell-registration_status="{ row }">
                    <Badge :variant="row.original.status_badge_color">
                        {{ formatStatusName(row.original.registration_status) }}
                    </Badge>
                </template>

                <template #cell-completion_status="{ row }">
                    <Badge :variant="row.original.status_badge_color">
                        {{ formatStatusName(row.original.completion_status) }}
                    </Badge>
                </template>

                <template #cell-grade_status="{ row }">
                    <div v-if="row.original.grade_status">
                        <Badge :variant="row.original.grade_badge_color">
                            {{ formatStatusName(row.original.grade_status) }}
                        </Badge>
                        <p v-if="row.original.final_grade" class="mt-1 text-xs text-gray-500">
                            Grade: {{ row.original.final_grade }}
                            <span v-if="row.original.final_percentage">({{ row.original.final_percentage }}%)</span>
                        </p>
                    </div>
                    <span v-else class="text-gray-400">-</span>
                </template>

                <template #cell-pass_fail_status="{ row }">
                    <Badge v-if="row.original.pass_fail_status" :variant="row.original.pass_fail_badge_color">
                        {{ row.original.pass_fail_status === 'pass' ? 'Pass' : 'Fail' }}
                    </Badge>
                    <span v-else class="text-gray-400">-</span>
                </template>

                <template #cell-retake="{ row }">
                    <div class="flex items-center gap-2">
                        <Badge v-if="row.original.is_retake" variant="outline" class="text-orange-600"> Attempt {{ row.original.attempt_number }} </Badge>
                        <span v-else class="text-gray-400">-</span>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <TooltipProvider :delay-duration="0">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="showDetails(row.original)">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>View Details</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Mobile Card View -->
        <div class="space-y-4 md:hidden">
            <div v-if="loading" class="space-y-4">
                <div v-for="i in 3" :key="i" class="animate-pulse">
                    <div class="h-32 rounded-lg bg-gray-200"></div>
                </div>
            </div>

            <div v-else-if="registrations.data.length === 0" class="py-8 text-center">
                <BookOpen class="mx-auto h-12 w-12 text-gray-400" />
                <p class="mt-4 text-gray-500">No registrations found</p>
                <p class="text-sm text-gray-400">Try adjusting your filters</p>
            </div>

            <div v-else class="space-y-4">
                <div v-for="registration in registrations.data" :key="registration.id" class="rounded-lg border bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="font-medium text-blue-600">
                                <Link :href="route('course-offerings.show', registration.course_offering_id)" class="hover:underline">
                                    {{ registration.course_name }}
                                </Link>
                            </h3>
                            <p class="text-sm text-gray-500">
                                {{ registration.course_code }}
                                <span v-if="registration.section_code && registration.section_code !== 'N/A'"> — Section {{ registration.section_code }}</span>
                            </p>
                        </div>
                        <Badge :variant="registration.status_badge_color">
                            {{ formatStatusName(registration.completion_status) }}
                        </Badge>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="font-medium text-gray-700">Semester</p>
                            <p class="text-gray-600">{{ registration.semester }}</p>
                            <p class="text-gray-500">{{ registration.academic_year }}</p>
                        </div>
                        <div>
                            <p class="font-medium text-gray-700">Grade</p>
                            <div v-if="registration.grade_status">
                                <Badge :variant="registration.grade_badge_color">
                                    {{ formatStatusName(registration.grade_status) }}
                                </Badge>
                                <p v-if="registration.final_grade" class="mt-1 text-xs text-gray-500">
                                    {{ registration.final_grade }}
                                    <span v-if="registration.final_percentage">({{ registration.final_percentage }}%)</span>
                                </p>
                            </div>
                            <span v-else class="text-gray-400">Not graded</span>
                        </div>
                    </div>

                    <div v-if="registration.pass_fail_status" class="mt-3 flex items-center gap-2">
                        <p class="text-sm font-medium text-gray-700">Result:</p>
                        <Badge :variant="registration.pass_fail_badge_color">
                            {{ registration.pass_fail_status === 'pass' ? 'Pass' : 'Fail' }}
                        </Badge>
                        <span v-if="registration.meets_attendance_requirement === false" class="text-xs text-orange-600">(Low Attendance)</span>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">{{ registration.credit_points ?? 0 }} pts</span>
                            <Badge v-if="registration.is_retake" variant="outline" class="text-orange-600"> Attempt {{ registration.attempt_number }} </Badge>
                        </div>
                        <Button variant="ghost" size="sm" @click="showDetails(registration)">
                            <Eye class="mr-2 h-4 w-4" />
                            Details
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <DataPagination :pagination-data="paginationData" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

        <!-- Details Modal -->
        <Dialog v-model:open="isDetailsModalOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Registration Details</DialogTitle>
                    <DialogDescription> Detailed information about this course registration </DialogDescription>
                </DialogHeader>

                <div v-if="selectedRegistration" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label class="text-sm font-medium">Course</Label>
                            <p class="text-sm text-gray-600">{{ selectedRegistration.course_name }}</p>
                            <p class="text-sm text-gray-500">{{ selectedRegistration.course_code }}</p>
                        </div>
                        <div>
                            <Label class="text-sm font-medium">Semester</Label>
                            <p class="text-sm text-gray-600">{{ selectedRegistration.semester }}</p>
                            <p class="text-sm text-gray-500">{{ selectedRegistration.academic_year }}</p>
                        </div>
                        <div>
                            <Label class="text-sm font-medium">Status</Label>
                            <Badge :variant="selectedRegistration.status_badge_color">
                                {{ formatStatusName(selectedRegistration.registration_status) }}
                            </Badge>
                        </div>
                        <div>
                            <Label class="text-sm font-medium">Credits</Label>
                            <p class="text-sm text-gray-600">{{ selectedRegistration.credit_points }} credit points (equivalent)</p>
                            <p class="text-sm text-gray-500">{{ selectedRegistration.credit_points_earned }} credit points (earned)</p>
                        </div>
                    </div>

                    <Separator />

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label class="text-sm font-medium">Registration Date</Label>
                            <p class="text-sm text-gray-600">{{ selectedRegistration.formatted_registration_date }}</p>
                            <p class="text-sm text-gray-500">{{ selectedRegistration.registration_method }}</p>
                        </div>
                        <div v-if="selectedRegistration.completion_date">
                            <Label class="text-sm font-medium">Completion Date</Label>
                            <p class="text-sm text-gray-600">{{ selectedRegistration.formatted_completion_date }}</p>
                        </div>
                        <div v-if="selectedRegistration.drop_date">
                            <Label class="text-sm font-medium">Drop Date</Label>
                            <p class="text-sm text-gray-600">{{ formatDate(selectedRegistration.drop_date) }}</p>
                        </div>
                        <div v-if="selectedRegistration.withdrawal_date">
                            <Label class="text-sm font-medium">Withdrawal Date</Label>
                            <p class="text-sm text-gray-600">{{ formatDate(selectedRegistration.withdrawal_date) }}</p>
                        </div>
                    </div>

                    <div v-if="selectedRegistration.final_grade" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label class="text-sm font-medium">Final Grade</Label>
                            <div class="flex items-center gap-2">
                                <Badge :variant="selectedRegistration.grade_badge_color">
                                    {{ selectedRegistration.final_grade }}
                                </Badge>
                                <span v-if="selectedRegistration.final_percentage" class="text-sm text-gray-500">{{ selectedRegistration.final_percentage }}%</span>
                                <span v-else-if="selectedRegistration.grade_points" class="text-sm text-gray-500">{{ selectedRegistration.grade_points }} points</span>
                            </div>
                        </div>
                        <div>
                            <Label class="text-sm font-medium">Result Status</Label>
                            <div class="flex items-center gap-2">
                                <Badge v-if="selectedRegistration.pass_fail_status" :variant="selectedRegistration.pass_fail_badge_color">
                                    {{ selectedRegistration.pass_fail_status === 'pass' ? 'Pass' : 'Fail' }}
                                </Badge>
                                <Badge v-else :variant="selectedRegistration.is_passing_grade ? 'success' : 'destructive'">
                                    {{ selectedRegistration.is_passing_grade ? 'Passing' : 'Failing' }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedRegistration.meets_attendance_requirement !== null || selectedRegistration.grade_status" class="rounded-lg bg-blue-50 p-4">
                        <h4 class="mb-2 font-medium text-blue-900">Academic Record Details</h4>
                        <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                            <div v-if="selectedRegistration.meets_attendance_requirement !== null">
                                <Label class="text-sm font-medium">Attendance Requirement</Label>
                                <Badge :variant="selectedRegistration.meets_attendance_requirement ? 'success' : 'destructive'">
                                    {{ selectedRegistration.meets_attendance_requirement ? 'Met' : 'Not Met' }}
                                </Badge>
                            </div>
                            <div v-if="selectedRegistration.grade_status">
                                <Label class="text-sm font-medium">Grade Status</Label>
                                <Badge :variant="selectedRegistration.grade_status === 'passing' ? 'success' : 'destructive'">
                                    {{ selectedRegistration.grade_status }}
                                </Badge>
                            </div>
                            <div v-if="selectedRegistration.completion_status">
                                <Label class="text-sm font-medium">Completion Status</Label>
                                <Badge :variant="selectedRegistration.completion_status === 'completed' ? 'success' : 'warning'">
                                    {{ selectedRegistration.completion_status }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedRegistration.is_retake" class="rounded-lg bg-orange-50 p-4">
                        <h4 class="mb-2 font-medium text-orange-900">Retake Information</h4>
                        <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                            <div>
                                <Label class="text-sm font-medium">Attempt Number</Label>
                                <p class="text-orange-700">{{ selectedRegistration.attempt_number }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium">Retake Fee</Label>
                                <p class="text-orange-700">${{ selectedRegistration.retake_fee }}</p>
                            </div>
                            <div>
                                <Label class="text-sm font-medium">Fee Payment Status</Label>
                                <Badge :variant="selectedRegistration.is_retake_paid === 'yes' ? 'success' : 'warning'">
                                    {{ selectedRegistration.is_retake_paid === 'yes' ? 'Paid' : 'Unpaid' }}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedRegistration.notes" class="rounded-lg bg-gray-50 p-4">
                        <Label class="text-sm font-medium">Notes</Label>
                        <p class="mt-1 text-sm text-gray-600">{{ selectedRegistration.notes }}</p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
