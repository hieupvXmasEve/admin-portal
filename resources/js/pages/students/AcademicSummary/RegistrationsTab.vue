<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { BookOpen, CheckCircle, Clock, Eye, RotateCcw, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
import type { CourseRegistrationRecord, RegistrationsData, RegistrationsFilters } from '@/types/models';

interface Props {
    registrations: RegistrationsData;
    studentId: number;
}

const props = defineProps<Props>();

// Reactive state
const loading = ref(false);
const isDetailsModalOpen = ref(false);
const selectedRegistration = ref<CourseRegistrationRecord | null>(null);

// Filters
const filters = ref<RegistrationsFilters>({
    academic_year: props.registrations.filters.academic_year || '',
    semester_id: props.registrations.filters.semester_id || '',
    status: props.registrations.filters.status || '',
    is_retake: props.registrations.filters.is_retake || '',
    per_page: props.registrations.filters.per_page || 50,
});

// Computed properties
const hasActiveFilters = computed(() => {
    return filters.value.academic_year || filters.value.semester_id || filters.value.status || filters.value.is_retake;
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
        prev_page_url: null, // Will be constructed by navigation handler
        next_page_url: null, // Will be constructed by navigation handler
        links: [], // Will be constructed by navigation handler
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
        enableSorting: true,
        cell: 'course',
    },
    {
        header: 'Semester',
        id: 'semester',
        enableSorting: true,
        cell: 'semester',
    },
    {
        header: 'Status',
        id: 'status',
        enableSorting: true,
        cell: 'status',
    },
    {
        header: 'Grade',
        id: 'grade',
        enableSorting: true,
        cell: 'grade',
    },
    {
        header: 'Credits',
        accessorKey: 'credit_hours',
        enableSorting: true,
        cell: ({ row }) => `${row.original.credit_hours} hrs`,
    },
    {
        header: 'Retake',
        id: 'retake',
        enableSorting: false,
        cell: 'retake',
    },
    {
        header: 'Registration Date',
        accessorKey: 'formatted_registration_date',
        enableSorting: true,
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
const formatStatusName = (status: string): string => {
    return status.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const applyFilters = (): void => {
    loading.value = true;

    const params = new URLSearchParams();

    if (filters.value.academic_year) params.set('registrations[academic_year]', filters.value.academic_year);
    if (filters.value.semester_id) params.set('registrations[semester_id]', filters.value.semester_id.toString());
    if (filters.value.status) params.set('registrations[status]', filters.value.status);
    if (filters.value.is_retake) params.set('registrations[is_retake]', filters.value.is_retake);
    if (filters.value.per_page) params.set('per_page', filters.value.per_page.toString());
    console.log(params.toString());

    const url = `/students/${props.studentId}/academic-summary${params.toString() ? '?tab=registrations&' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['registrations'],
        onFinish: () => {
            loading.value = false;
        },
    });
};

const clearFilters = (): void => {
    filters.value = {
        academic_year: '',
        semester_id: 0,
        status: 'all',
        is_retake: 'all',
        per_page: 50,
    };
    applyFilters();
};

const handlePaginationNavigate = (url: string): void => {
    loading.value = true;
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['registrations'],
        onFinish: () => {
            loading.value = false;
        },
    });
};

const handlePageSizeChange = (pageSize: number): void => {
    filters.value.per_page = pageSize;
    applyFilters();
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
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
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

            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Retakes</p>
                        <p class="text-2xl font-bold text-orange-600">{{ registrations.summary.retakes }}</p>
                        <p class="text-xs text-gray-500">{{ registrations.summary.retake_rate }}% retake rate</p>
                    </div>
                    <div class="rounded-full bg-orange-50 p-3">
                        <RotateCcw class="h-6 w-6 text-orange-600" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-[200px] flex-1">
                    <Label for="academic-year-filter">Academic Year</Label>
                    <Select v-model="filters.academic_year" @update:model-value="applyFilters">
                        <SelectTrigger id="academic-year-filter">
                            <SelectValue placeholder="All Academic Years" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Academic Years</SelectItem>
                            <SelectItem v-for="group in registrations.semester_groups" :key="group.academic_year" :value="group.academic_year">
                                {{ group.academic_year }} ({{ group.total_registrations }} courses)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[200px] flex-1">
                    <Label for="semester-filter">Semester</Label>
                    <Select v-model="filters.semester_id" @update:model-value="applyFilters">
                        <SelectTrigger id="semester-filter">
                            <SelectValue placeholder="All Semesters" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <template v-for="group in registrations.semester_groups" :key="group.academic_year">
                                <SelectItem v-for="semester in group.semesters" :key="semester.id" :value="semester.id.toString()">
                                    {{ semester.name }} ({{ semester.academic_year }})
                                </SelectItem>
                            </template>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[200px] flex-1">
                    <Label for="status-filter">Status</Label>
                    <Select v-model="filters.status" @update:model-value="applyFilters">
                        <SelectTrigger id="status-filter">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem v-for="status in registrations.status_breakdown" :key="status.status" :value="status.status">
                                {{ formatStatusName(status.status) }} ({{ status.count }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="min-w-[150px] flex-1">
                    <Label for="retake-filter">Retakes</Label>
                    <Select v-model="filters.is_retake" @update:model-value="applyFilters">
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
            <DataTable :data="registrations.data" :columns="columns" :loading="loading">
                <template #cell-course="{ row }">
                    <div>
                        <p class="font-medium text-gray-900">{{ row.original.course_name }}</p>
                        <p class="text-sm text-gray-500">{{ row.original.course_code }}</p>
                    </div>
                </template>

                <template #cell-semester="{ row }">
                    <div>
                        <p class="font-medium text-gray-900">{{ row.original.semester }}</p>
                        <p class="text-sm text-gray-500">{{ row.original.academic_year }}</p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <Badge :variant="row.original.status_badge_color">
                        {{ formatStatusName(row.original.registration_status) }}
                    </Badge>
                </template>

                <template #cell-grade="{ row }">
                    <div v-if="row.original.final_grade">
                        <Badge :variant="row.original.grade_badge_color">
                            {{ row.original.final_grade }}
                        </Badge>
                        <p class="mt-1 text-xs text-gray-500">{{ row.original.grade_points }} pts</p>
                    </div>
                    <span v-else class="text-gray-400">-</span>
                </template>

                <template #cell-retake="{ row }">
                    <div class="flex items-center gap-2">
                        <Badge v-if="row.original.is_retake" variant="outline" class="text-orange-600">
                            Attempt {{ row.original.attempt_number }}
                        </Badge>
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
                            <h3 class="font-medium text-gray-900">{{ registration.course_name }}</h3>
                            <p class="text-sm text-gray-500">{{ registration.course_code }}</p>
                        </div>
                        <Badge :variant="registration.status_badge_color">
                            {{ formatStatusName(registration.registration_status) }}
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
                            <div v-if="registration.final_grade">
                                <Badge :variant="registration.grade_badge_color">
                                    {{ registration.final_grade }}
                                </Badge>
                                <p class="mt-1 text-gray-500">{{ registration.grade_points }} pts</p>
                            </div>
                            <span v-else class="text-gray-400">Not graded</span>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">{{ registration.credit_hours }} credits</span>
                            <Badge v-if="registration.is_retake" variant="outline" class="text-orange-600">
                                Attempt {{ registration.attempt_number }}
                            </Badge>
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
                            <p class="text-sm text-gray-600">{{ selectedRegistration.credit_hours }} credit hours</p>
                            <p class="text-sm text-gray-500">{{ selectedRegistration.credit_points }} credit points</p>
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
                                <span class="text-sm text-gray-500">{{ selectedRegistration.grade_points }} points</span>
                            </div>
                        </div>
                        <div>
                            <Label class="text-sm font-medium">Grade Status</Label>
                            <Badge :variant="selectedRegistration.is_passing_grade ? 'success' : 'destructive'">
                                {{ selectedRegistration.is_passing_grade ? 'Passing' : 'Failing' }}
                            </Badge>
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
