<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PaginatedResponse } from '@/types';
import type { Program, Semester, Specialization, Student } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertCircle, CheckCircle, Eye, GraduationCap, Loader2, UserPlus, Users } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
interface Props {
    students: PaginatedResponse<Student>;
    filters?: {
        search?: string;
        program_id?: number;
        specialization_id?: number;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    programs: Program[];
    specializations: Specialization[];
    current_campus_id: number;
    current_semester: Semester;
}

const props = defineProps<Props>();

// Reactive data
const data = computed(() => props.students.data);

// Filter state - Initialize with props or defaults
const filters = ref({
    search: props.filters?.search || '',
    program_id: props.filters?.program_id ? props.filters.program_id.toString() : 'all',
    specialization_id: props.filters?.specialization_id ? props.filters.specialization_id.toString() : 'all',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

// Bulk onboarding dialog and state
const bulkOnboardingDialog = ref(false);
const isProcessing = ref(false);
const processingStep = ref('');
const enrollmentResults = ref<{ success: boolean; message: string; details: any; errors: any } | null>(null);

// Filter specializations based on selected program
const filteredSpecializations = computed(() => {
    if (filters.value.program_id === 'all') {
        return props.specializations;
    }
    return props.specializations.filter((spec) => spec.program_id.toString() === filters.value.program_id);
});

// Column definitions (without selection)
const columns: ColumnDef<Student>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.students.current_page;
            const perPage = props.students.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        accessorKey: 'student_id',
        header: 'Student ID',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'font-medium' }, student.student_id);
        },
    },
    {
        accessorKey: 'full_name',
        header: 'Name',
        enableSorting: true,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'font-medium' }, student.full_name);
        },
    },
    {
        accessorKey: 'email',
        header: 'Email',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'text-muted-foreground' }, student.email);
        },
    },
    {
        accessorKey: 'program.name',
        header: 'Program',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'text-sm' }, student.program?.name || 'N/A');
        },
    },
    {
        accessorKey: 'specialization.name',
        header: 'Specialization',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original;
            return h('div', { class: 'text-sm' }, student.specialization?.name || 'N/A');
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original;
            const statusColors = {
                active: 'bg-green-100 text-green-800',
                inactive: 'bg-gray-100 text-gray-800',
                suspended: 'bg-red-100 text-red-800',
                graduated: 'bg-blue-100 text-blue-800',
            };
            return h(
                'span',
                {
                    class: `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        statusColors[student.status as keyof typeof statusColors] || statusColors.inactive
                    }`,
                },
                student.status,
            );
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

// Server-side filtering functions following programs/Index.vue pattern
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.program_id !== 'all') params.set('program_id', newFilters.program_id);
    if (newFilters.specialization_id !== 'all') params.set('specialization_id', newFilters.specialization_id);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/students/new-students${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

// Update filters handler
const updateFilters = () => {
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        program_id: 'all',
        specialization_id: 'all',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    router.visit('/students/new-students', {
        preserveState: true,
        preserveScroll: true,
        only: ['students', 'filters'],
    });
};

// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};

// Complete student onboarding - Apply to all filtered students
const executeCompleteOnboarding = () => {
    if (props.students.total === 0) {
        alert('No students found to complete onboarding');
        return;
    }

    isProcessing.value = true;
    processingStep.value = 'Initializing automated enrollment...';
    enrollmentResults.value = null;

    router.post(
        '/students/new-students/bulk-onboarding',
        {
            // Send current filters to apply to all matching students
            filters: {
                search: filters.value.search || null,
                program_id: filters.value.program_id !== 'all' ? parseInt(filters.value.program_id) : null,
                specialization_id: filters.value.specialization_id !== 'all' ? parseInt(filters.value.specialization_id) : null,
            },
            semester_id: props.current_semester.id,
        },
        {
            onStart: () => {
                processingStep.value = 'Processing student enrollments...';
            },
            onProgress: () => {
                processingStep.value = 'Creating course offerings and registrations...';
            },
            onSuccess: (page: any) => {
                isProcessing.value = false;
                processingStep.value = '';

                // Store results for display
                if (page.props.flash?.success) {
                    toast.success(page.props.flash.success);
                    enrollmentResults.value = {
                        success: true,
                        message: page.props.flash.success,
                        details: page.props.enrollmentData || null,
                        errors: null,
                    };
                }

                // Keep dialog open to show results
                setTimeout(() => {
                    bulkOnboardingDialog.value = false;
                    enrollmentResults.value = null;
                    // Refresh the page to show updated data
                    router.reload({ only: ['students'] });
                }, 5000);
            },
            onError: (errors) => {
                isProcessing.value = false;
                processingStep.value = '';
                enrollmentResults.value = {
                    success: false,
                    message: 'Failed to complete automated enrollment',
                    errors: errors,
                    details: null,
                };
            },
        },
    );
};
</script>

<template>
    <Head title="New Students" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">New Students</h1>
                <p class="text-muted-foreground text-sm">Manage first-semester students with filtering and bulk operations</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total New Students</CardTitle>
                    <Users class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ students.total }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Filtered Students</CardTitle>
                    <UserPlus class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ students.data.length }}</div>
                    <p class="text-muted-foreground text-xs">on this page</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="pt-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <!-- Search -->
                    <DebouncedInput v-model="filters.search" placeholder="Search students..." @debounced="updateFilters" class="max-w-sm" />

                    <!-- Program Filter -->
                    <Select v-model="filters.program_id" @update:model-value="updateFilters">
                        <SelectTrigger>
                            <SelectValue placeholder="Select Program" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Programs</SelectItem>
                            <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                {{ program.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Specialization Filter -->
                    <Select v-model="filters.specialization_id" @update:model-value="updateFilters">
                        <SelectTrigger>
                            <SelectValue placeholder="Select Specialization" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Specializations</SelectItem>
                            <SelectItem
                                v-for="specialization in filteredSpecializations"
                                :key="specialization.id"
                                :value="specialization.id.toString()"
                            >
                                {{ specialization.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Clear Filters -->
                    <Button variant="outline" @click="clearFilters"> Clear Filters </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Bulk Operations -->
        <Card>
            <CardContent class="pt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-medium">Automated Enrollment</h3>
                        <p class="text-muted-foreground text-xs">
                            Automatically enroll all {{ students.total }} filtered students with course assignments
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <!-- Complete Student Onboarding Dialog -->
                        <Dialog v-model:open="bulkOnboardingDialog">
                            <DialogTrigger as-child>
                                <Button variant="default" size="sm">
                                    <GraduationCap class="mr-2 h-4 w-4" />
                                    Start Automated Enrollment
                                </Button>
                            </DialogTrigger>
                            <DialogContent class="max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>Automated Student Enrollment</DialogTitle>
                                </DialogHeader>
                                <div class="space-y-6">
                                    <!-- Processing State -->
                                    <div v-if="isProcessing" class="space-y-4">
                                        <div class="flex items-center gap-3">
                                            <Loader2 class="h-5 w-5 animate-spin text-blue-600" />
                                            <span class="text-sm font-medium">{{ processingStep }}</span>
                                        </div>
                                        <div class="h-2 w-full rounded-full bg-gray-200">
                                            <div class="h-2 rounded-full bg-blue-600 transition-all duration-300" style="width: 33%"></div>
                                        </div>
                                        <p class="text-muted-foreground text-xs">Please wait while we process the automated enrollment...</p>
                                    </div>

                                    <!-- Results Display -->
                                    <div v-else-if="enrollmentResults" class="space-y-4">
                                        <div
                                            :class="[
                                                'rounded-lg border p-4',
                                                enrollmentResults.success
                                                    ? 'border-green-200 bg-green-50 text-green-800'
                                                    : 'border-red-200 bg-red-50 text-red-800',
                                            ]"
                                        >
                                            <div class="mb-2 flex items-center gap-2">
                                                <CheckCircle v-if="enrollmentResults.success" class="h-5 w-5" />
                                                <AlertCircle v-else class="h-5 w-5" />
                                                <h4 class="font-medium">
                                                    {{ enrollmentResults.success ? 'Enrollment Completed!' : 'Enrollment Failed' }}
                                                </h4>
                                            </div>
                                            <div class="text-sm whitespace-pre-line">
                                                {{ enrollmentResults.message }}
                                            </div>
                                        </div>

                                        <div v-if="enrollmentResults.success" class="text-muted-foreground text-xs">
                                            This dialog will close automatically in a few seconds...
                                        </div>
                                    </div>

                                    <!-- Initial State -->
                                    <div v-else class="space-y-4">
                                        <div class="space-y-2">
                                            <p class="text-muted-foreground text-sm">This automated process will:</p>
                                            <ul class="text-muted-foreground ml-4 space-y-1 text-sm">
                                                <li>• Create semester enrollments based on student curriculum</li>
                                                <li>• Open course offerings for required first-year units</li>
                                                <li>• Register students for appropriate courses automatically</li>
                                                <li>• Handle capacity management and conflict resolution</li>
                                            </ul>
                                        </div>

                                        <div class="rounded-lg bg-blue-50 p-3">
                                            <div class="mb-2 flex items-center gap-2">
                                                <Users class="h-4 w-4 text-blue-600" />
                                                <span class="text-sm font-medium text-blue-900">Processing Summary</span>
                                            </div>
                                            <div class="space-y-1 text-sm text-blue-800">
                                                <div>
                                                    Students to process: <strong>{{ students.total }}</strong>
                                                </div>
                                                <div>
                                                    Target semester: <strong>{{ current_semester.name }} - {{ current_semester.code }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-2">
                                            <Button variant="outline" @click="bulkOnboardingDialog = false" :disabled="isProcessing"> Cancel </Button>
                                            <Button @click="executeCompleteOnboarding" :disabled="isProcessing">
                                                <GraduationCap class="mr-2 h-4 w-4" />
                                                Start Automated Enrollment
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Data Table -->
        <DataTable :data="data" :columns="columns">
            <template #cell-actions="{ row }">
                <div class="flex items-center gap-2">
                    <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button variant="ghost" size="sm" @click="router.visit(studentRoutes.show(row.original.id))" title="View student">
                                    <Eye class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>View student</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </template>
        </DataTable>

        <!-- Pagination -->
        <DataPagination :pagination-data="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
