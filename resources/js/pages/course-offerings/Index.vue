<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import type { PaginatedResponse } from '@/types';
import type { CourseOffering, Semester } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { BarChart3, CheckCircle, Copy, Edit, Eye, MoreHorizontal, Plus, ToggleLeft, ToggleRight, Trash2 } from 'lucide-vue-next';
import { h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface CourseOfferingFilters {
    search: string;
    semester_id: string;
    enrollment_status: string;
    course_status: string;
    delivery_mode: string;
    unit_level: string;
    unit_type: string;
    page: number;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface Props {
    courseOfferings: PaginatedResponse<CourseOffering>;
    filters: CourseOfferingFilters;
    semesters: Semester[];
    unitLevels: { value: string; label: string }[];
    unitTypes: { value: string; label: string }[];
    enrollmentStatusOptions: { value: string; label: string }[];
    courseStatusOptions: { value: string; label: string }[];
    deliveryModeOptions: { value: string; label: string }[];
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    surveyForms: { id: number; title: string; code: string }[];
}
const props = defineProps<Props>();

const { filters, handleSearch, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange } = useInertiaFilters<CourseOfferingFilters>({
    baseUrl: '/course-offerings',
    initialFilters: {
        ...props.filters,
        search: props.filters.search || '',
    },
    only: ['courseOfferings', 'filters'],
    defaultValues: {
        search: '',
        semester_id: '',
        enrollment_status: 'all',
        course_status: 'all',
        delivery_mode: 'all',
        unit_level: 'all',
        unit_type: 'all',
        page: 1,
        per_page: 15,
        sort: 'units.code',
        direction: 'asc',
    },
    debounce: 300,
});

const selectedItems = ref<number[]>([]);
const isLoading = ref(false);
const statistics = ref<any>(null);
const showStatusDialog = ref(false);
const selectedCourse = ref<CourseOffering | null>(null);
const showSurveyDialog = ref(false);
const selectedFormId = ref<string>('');

// Initialize the confirm dialog composable
const confirmDialog = useGlobalConfirmDialog();
const api = useApi();

// Load statistics
const loadStatistics = async () => {
    try {
        const semesterId = filters.semester_id === 'all' ? '' : filters.semester_id;
        const response = await fetch(`/api/course-offerings/statistics?semester_id=${semesterId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        if (data.success) {
            statistics.value = data.data;
        } else {
            console.error('API returned error:', data);
        }
    } catch (error) {
        console.error('Failed to load statistics:', error);
        // Set default statistics on error
        statistics.value = {
            total_offerings: 0,
            active_offerings: 0,
            total_enrollment: 0,
            total_capacity: 0,
            enrollment_rate: 0,
        };
    }
};

watch(
    () => filters.semester_id,
    () => {
        loadStatistics();
    },
);

// Load statistics on mount
loadStatistics();

// Old filter handlers removed in favor of useInertiaFilters

const toggleStatus = (courseOffering: CourseOffering) => {
    router.patch(
        `/course-offerings/${courseOffering.id}/toggle-status`,
        {},
        {
            onSuccess: () => {
                toast.success('Course offering status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update course offering status');
            },
        },
    );
};

const deleteCourseOffering = (courseOffering: CourseOffering) => {
    const unitName = courseOffering.unit?.name || 'Unknown Unit';
    const unitCode = courseOffering.unit?.code || '';
    const itemName = unitCode ? `${unitCode} - ${unitName}` : unitName;

    confirmDialog.confirmDelete(itemName, 'course offering', () => {
        return new Promise((resolve, reject) => {
            router.delete(`/course-offerings/${courseOffering.id}`, {
                onSuccess: (page) => {
                    // Check if there's a flash error message (indicates deletion failed)
                    if ((page.props as any).flash?.error) {
                        toast.error((page.props as any).flash.error as string);
                        reject(new Error((page.props as any).flash.error as string));
                    } else {
                        // Show success message from flash or default
                        const successMessage = ((page.props as any).flash?.success as string) || 'Course offering deleted successfully';
                        toast.success(successMessage);
                        resolve();
                    }
                },
                onError: (errors) => {
                    console.error('Failed to delete course offering:', errors);
                    // Show first error message if available
                    const firstError = Object.values(errors)[0];
                    const errorMessage = Array.isArray(firstError) ? firstError[0] : (firstError as string) || 'Failed to delete course offering';
                    toast.error(errorMessage);
                    reject(new Error(errorMessage));
                },
            });
        });
    });
};

const duplicateCourseOffering = (courseOffering: CourseOffering) => {
    confirmDialog.showConfirmDialog(
        {
            title: 'Confirm Course Offering Duplication',
            message: 'Are you sure you want to duplicate this course offering? Note: Lecturers will not be duplicated.',
            confirmText: 'Duplicate',
        },
        {
            onConfirm: () => {
                return new Promise((resolve, reject) => {
                    router.post(
                        `/course-offerings/${courseOffering.id}/duplicate`,
                        {},
                        {
                            onSuccess: () => {
                                toast.success('Course offering duplicated successfully');
                                resolve();
                            },
                            onError: () => {
                                toast.error('Failed to duplicate course offering');
                                reject(new Error('Failed to duplicate course offering'));
                            },
                        },
                    );
                });
            },
        },
    );
};

const openSurveyDialog = (courseOffering: CourseOffering) => {
    selectedCourse.value = courseOffering;
    // Auto select if only one form available
    if (props.surveyForms.length === 1) {
        selectedFormId.value = props.surveyForms[0].id.toString();
    } else {
        selectedFormId.value = '';
    }
    showSurveyDialog.value = true;
};

const closeSurveyDialog = () => {
    showSurveyDialog.value = false;
    selectedCourse.value = null;
    selectedFormId.value = '';
};

const handleCreateSurvey = () => {
    if (!selectedCourse.value || !selectedFormId.value) {
        toast.error('Please select a survey form');
        return;
    }

    router.post(
        `/course-offerings/${selectedCourse.value.id}/survey`,
        { form_id: selectedFormId.value },
        {
            onSuccess: () => {
                toast.success('Survey created and assigned successfully');
                closeSurveyDialog();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(Array.isArray(firstError) ? firstError[0] : (firstError as string) || 'Failed to create survey');
            },
        },
    );
};

const openStatusDialog = (courseOffering: CourseOffering) => {
    selectedCourse.value = courseOffering;
    showStatusDialog.value = true;
};

const closeStatusDialog = () => {
    showStatusDialog.value = false;
    selectedCourse.value = null;
};

const updateCourseStatus = async () => {
    if (!selectedCourse.value) return;
    isLoading.value = true;

    const { data: apiData } = await api.post(`/api/course-offerings/${selectedCourse.value.id}/complete`, {});

    isLoading.value = false;
    console.log('apiData.value', apiData.value);
    if (apiData.value?.success) {
        toast.success(apiData.value.message);
        closeStatusDialog();
        router.reload();
        loadStatistics();
    } else {
        toast.error(apiData.value?.message || 'Failed to update course status');
    }
};

const bulkDelete = () => {
    if (selectedItems.value.length === 0) {
        toast.error('Please select items to delete');
        return;
    }

    confirmDialog.showConfirmDialog(
        {
            title: 'Delete Course Offerings',
            message: `Are you sure you want to delete ${selectedItems.value.length} course offering(s)? This will also remove all associated student registrations and cannot be undone.`,
            confirmText: `Delete ${selectedItems.value.length} Offering(s)`,
        },
        {
            onConfirm: () => {
                return new Promise((resolve, reject) => {
                    router.delete('/api/course-offerings/bulk-delete', {
                        data: { ids: selectedItems.value },
                        onSuccess: (page) => {
                            // Check if there's a flash error message (indicates deletion failed)
                            if ((page.props as any).flash?.error) {
                                toast.error((page.props as any).flash.error as string);
                                reject(new Error((page.props as any).flash.error as string));
                            } else {
                                // Show success message from flash or default
                                const successMessage = ((page.props as any).flash?.success as string) || 'Course offerings deleted successfully';
                                toast.success(successMessage);
                                selectedItems.value = [];
                                resolve();
                            }
                        },
                        onError: (errors) => {
                            console.error('Failed to delete course offerings:', errors);
                            // Show first error message if available
                            const firstError = Object.values(errors)[0];
                            const errorMessage = Array.isArray(firstError) ? firstError[0] : (firstError as string) || 'Failed to delete course offerings';
                            toast.error(errorMessage);
                            reject(new Error(errorMessage));
                        },
                    });
                });
            },
        },
    );
};

const getCourseStatusBadge = (status: string) => {
    switch (status) {
        case 'not_started':
            return { label: 'Not Started', variant: 'secondary' as const };
        case 'in_progress':
            return { label: 'In Progress', variant: 'default' as const };
        case 'completed':
            return { label: 'Completed', variant: 'outline' as const };
        case 'cancelled':
            return { label: 'Cancelled', variant: 'destructive' as const };
        default:
            return { label: status, variant: 'outline' as const };
    }
};

const getDeliveryModeBadge = (mode: string) => {
    switch (mode) {
        case 'in_person':
            return { label: 'In Person', variant: 'default' as const };
        case 'online':
            return { label: 'Online', variant: 'secondary' as const };
        case 'hybrid':
            return { label: 'Hybrid', variant: 'outline' as const };
        case 'blended':
            return { label: 'Blended', variant: 'outline' as const };
        default:
            return { label: mode, variant: 'outline' as const };
    }
};

// Table columns definition
const columns: ColumnDef<CourseOffering>[] = [
    // No number
    {
        id: 'number',
        header: 'No.',
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.index + 1 + (props.courseOfferings.current_page - 1) * props.courseOfferings.per_page);
        },
    },
    {
        accessorKey: 'unit.code',
        header: 'Unit',
        cell: ({ row }) => {
            const course = row.original;
            const unitCode = course.unit?.code || 'N/A';
            return h('div', { class: 'space-y-1' }, [h('div', { class: 'font-medium' }, unitCode), course.section_code && h('div', { class: 'text-sm text-muted-foreground' }, `Section: ${course.section_code}`)]);
        },
    },
    {
        accessorKey: 'unit.name',
        header: 'Unit Name',
        cell: ({ row }) => {
            const course = row.original;
            const unitName = course.unit?.name || 'N/A';
            const creditPoints = course.unit?.credit_points;
            return h('div', { class: 'max-w-xs' }, [h('div', { class: 'font-medium truncate' }, unitName), creditPoints && h('div', { class: 'text-sm text-muted-foreground' }, `${creditPoints} credits`)]);
        },
    },
    {
        accessorKey: 'unit.level',
        header: 'Level',
        cell: ({ row }) => {
            const course = row.original;
            const level = course.unit?.level ?? 0;
            return h('div', { class: 'text-left font-medium' }, `Level ${level}`);
        },
    },
    {
        accessorKey: 'unit.unit_type',
        header: 'Unit Type',
        cell: ({ row }) => {
            const course = row.original;
            const unitType = course.unit?.unit_type;
            const typeLabels = {
                general: 'General',
                egc: 'EGC',
                semi: 'Semiconductor',
                ai: 'AI',
                mkt: 'Marketing',
                ba: 'Business Admin',
                cs: 'Computer Science',
                ee: 'Electrical Eng',
                me: 'Mechanical Eng',
                fin: 'Finance',
            };
            const label = unitType ? typeLabels[unitType as keyof typeof typeLabels] || unitType : 'N/A';
            return h('div', { class: 'text-left' }, label);
        },
    },
    {
        accessorKey: 'semester',
        header: 'Semester',
        cell: ({ row }) => {
            const semester = row.original.semester;
            return semester ? h('div', {}, [h('div', { class: 'font-medium' }, semester.name), h('div', { class: 'text-sm text-muted-foreground' }, semester.code)]) : 'N/A';
        },
    },
    {
        id: 'survey',
        header: 'Survey',
        cell: ({ row }) => {
            const course = row.original;

            // Don't show survey column for EGC units
            if (course.unit?.unit_type === 'egc') {
                return h('span', { class: 'text-xs text-muted-foreground' }, '-');
            }

            const hasSurvey = course.form_targets && course.form_targets.length > 0;

            if (hasSurvey) {
                return h(Badge, { variant: 'outline', class: 'bg-green-50 text-green-700 border-green-200' }, () => 'Created');
            }

            if (!props.surveyForms || props.surveyForms.length === 0) {
                return h('span', { class: 'text-xs text-muted-foreground' }, 'No Forms');
            }

            return h(
                Button,
                {
                    variant: 'outline',
                    size: 'sm',
                    class: 'h-7 text-xs',
                    onClick: (e: Event) => {
                        e.stopPropagation(); // Prevent row click
                        openSurveyDialog(course);
                    },
                },
                () => 'Create Survey',
            );
        },
    },
    {
        accessorKey: 'enrollment',
        header: 'Enrollment',
        cell: ({ row }) => {
            const course = row.original;
            const percentage = course.max_capacity > 0 ? Math.round((course.current_enrollment / course.max_capacity) * 100) : 0;
            return h('div', { class: 'text-center' }, [h('div', { class: 'font-medium' }, `${course.current_enrollment}/${course.max_capacity}`), h('div', { class: 'text-sm text-muted-foreground' }, `${percentage}%`)]);
        },
    },
    {
        accessorKey: 'delivery_mode',
        header: 'Delivery Mode',
        cell: ({ row }) => {
            const mode = getDeliveryModeBadge(row.original.delivery_mode);
            return h(Badge, { variant: mode.variant }, () => mode.label);
        },
    },
    {
        accessorKey: 'course_status',
        header: 'Course Status',
        cell: ({ row }) => {
            const status = row.original.course_status || 'not_started';
            const badge = getCourseStatusBadge(status);
            return h(Badge, { variant: badge.variant }, () => badge.label);
        },
    },
    {
        accessorKey: 'lecture',
        header: 'Lecture',
        cell: ({ row }) => {
            const lecture = row.original.lecture;

            return lecture ? lecture.display_name : 'Not Assigned';
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enablePinning: true,
        cell: ({ row }) => {
            const course = row.original;
            const courseStatus = course.course_status || 'not_started';
            const isCompleted = courseStatus === 'completed';
            const isCancelled = courseStatus === 'cancelled';
            const canModify = !isCompleted && !isCancelled;

            const menuItems = [
                h(
                    DropdownMenuItem,
                    {
                        onClick: () => router.visit(`/course-offerings/${course.id}`),
                    },
                    () => [h(Eye, { class: 'mr-2 h-4 w-4' }), 'View Details'],
                ),
                h(
                    DropdownMenuItem,
                    {
                        onClick: () => {
                            const statisticsUrl = `/course-statistics/${course.id}/assessment-scores`;
                            router.visit(statisticsUrl);
                        },
                    },
                    () => [h(BarChart3, { class: 'mr-2 h-4 w-4' }), 'View Statistics'],
                ),
                h(
                    DropdownMenuItem,
                    {
                        onClick: () => duplicateCourseOffering(course),
                    },
                    () => [h(Copy, { class: 'mr-2 h-4 w-4' }), 'Duplicate'],
                ),
            ];

            if (canModify) {
                menuItems.push(
                    h(
                        DropdownMenuItem,
                        {
                            onClick: () => router.visit(`/course-offerings/${course.id}/edit`),
                        },
                        () => [h(Edit, { class: 'mr-2 h-4 w-4' }), 'Edit'],
                    ),
                    h(
                        DropdownMenuItem,
                        {
                            onClick: () => openStatusDialog(course),
                        },
                        () => [h(CheckCircle, { class: 'mr-2 h-4 w-4 text-green-600' }), 'Mark as Completed'],
                    ),
                    h(
                        DropdownMenuItem,
                        {
                            onClick: () => toggleStatus(course),
                        },
                        () => [course.enrollment_status === 'open' ? h(ToggleLeft, { class: 'mr-2 h-4 w-4' }) : h(ToggleRight, { class: 'mr-2 h-4 w-4' }), course.enrollment_status === 'open' ? 'Close Registration' : 'Open Registration'],
                    ),
                    h(
                        DropdownMenuItem,
                        {
                            onClick: () => deleteCourseOffering(course),
                            class: 'text-destructive',
                        },
                        () => [h(Trash2, { class: 'mr-2 h-4 w-4' }), 'Delete'],
                    ),
                );
            }

            return h(
                DropdownMenu,
                {},
                {
                    default: () => [h(DropdownMenuTrigger, { asChild: true }, () => h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () => h(MoreHorizontal, { class: 'h-4 w-4' }))), h(DropdownMenuContent, { align: 'end' }, () => menuItems)],
                },
            );
        },
    },
];
</script>

<template>
    <Head title="Course Offerings" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Course Offerings</h1>
            <p class="text-muted-foreground">Manage course opening periods and registration settings</p>
        </div>
        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="loadStatistics">
                <BarChart3 class="mr-2 h-4 w-4" />
                Refresh Stats
            </Button>
            <Link href="/course-offerings/create">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Create Course Offering
                </Button>
            </Link>
        </div>
    </div>

    <!-- Statistics -->
    <div v-if="statistics" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Offerings</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.total_offerings }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Active Offerings</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-green-600">{{ statistics.active_offerings }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Enrollment</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.total_enrollment }}</div>
                <p class="text-muted-foreground text-xs">of {{ statistics.total_capacity }} capacity</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Enrollment Rate</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ statistics.enrollment_rate }}%</div>
            </CardContent>
        </Card>
    </div>

    <!-- Filters -->
    <Card>
        <CardHeader>
            <CardTitle>Filters</CardTitle>
            <CardDescription>Filter course offerings by various criteria</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Search</label>
                    <DebouncedInput v-model="filters.search" @update:model-value="handleSearch" placeholder="Search courses..." :debounce="300" />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Semester</label>
                    <Select v-model="filters.semester_id">
                        <SelectTrigger>
                            <SelectValue placeholder="All Semesters" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Semesters</SelectItem>
                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                {{ semester.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Level</label>
                    <Select v-model="filters.unit_level">
                        <SelectTrigger>
                            <SelectValue placeholder="All Levels" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Levels</SelectItem>
                            <SelectItem v-for="level in unitLevels" :key="level.value" :value="level.value.toString()">
                                {{ level.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Unit Type</label>
                    <Select v-model="filters.unit_type">
                        <SelectTrigger>
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem v-for="type in unitTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Course Status</label>
                    <Select v-model="filters.course_status">
                        <SelectTrigger>
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem v-for="option in courseStatusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Delivery Mode</label>
                    <Select v-model="filters.delivery_mode">
                        <SelectTrigger>
                            <SelectValue placeholder="All Modes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Modes</SelectItem>
                            <SelectItem v-for="option in deliveryModeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Actions -->
    <div v-if="selectedItems.length > 0" class="bg-muted flex items-center justify-between rounded-lg p-4">
        <span class="text-sm font-medium">{{ selectedItems.length }} item(s) selected</span>
        <Button variant="destructive" size="sm" @click="bulkDelete">
            <Trash2 class="mr-2 h-4 w-4" />
            Delete Selected
        </Button>
    </div>

    <!-- Data Table -->
    <Card>
        <CardContent class="px-4">
            <DataTable :data="courseOfferings.data" :columns="columns" :loading="isLoading" v-model:selected="selectedItems" row-key="id" />
        </CardContent>
    </Card>

    <!-- Pagination -->
    <DataPagination :pagination-data="courseOfferings" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />

    <!-- Mark Completed Dialog -->
    <Dialog v-model:open="showStatusDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Mark Course as Completed</DialogTitle>
                <DialogDescription>
                    <template v-if="selectedCourse">
                        Are you sure you want to mark <strong>{{ selectedCourse.unit?.code }}</strong> <span v-if="selectedCourse.section_code"> - Section {{ selectedCourse.section_code }}</span> as completed?
                    </template>
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div class="space-y-3 rounded-md border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                    <div class="flex items-start gap-2">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-800 dark:text-yellow-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="flex-1 space-y-2">
                            <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">Marking as Completed will:</p>
                            <ul class="ml-1 space-y-1 text-sm text-yellow-800 dark:text-yellow-200">
                                <li>• Finalize all student grades</li>
                                <li>• Update course registrations to "completed" status</li>
                                <li>• Process EGC level progression (if applicable)</li>
                                <li>• <strong>Lock the course from further modifications</strong></li>
                            </ul>
                            <p class="border-t border-yellow-300 pt-2 text-sm font-semibold text-yellow-900 dark:border-yellow-700 dark:text-yellow-100">⚠️ Once marked as completed, this action cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="closeStatusDialog">Cancel</Button>
                <Button :disabled="isLoading" @click="updateCourseStatus">
                    <LoadingSpinner v-if="isLoading" size="sm" />
                    {{ isLoading ? 'Processing...' : 'Confirm Completion' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Create Survey Dialog -->
    <Dialog v-model:open="showSurveyDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Create Course Survey</DialogTitle>
                <DialogDescription>
                    <template v-if="selectedCourse">
                        Select a survey form for <strong>{{ selectedCourse.unit?.code }}</strong>
                        <span v-if="selectedCourse.section_code"> - Section {{ selectedCourse.section_code }}</span>
                    </template>
                </DialogDescription>
            </DialogHeader>
            <div class="min-w-0 space-y-4 overflow-hidden py-4">
                <div class="min-w-0 space-y-2 overflow-hidden">
                    <label class="text-sm font-medium">Select Survey Form</label>
                    <div class="grid w-full min-w-0 grid-cols-1 overflow-hidden">
                        <Select v-model="selectedFormId">
                            <SelectTrigger class="w-full min-w-0 overflow-hidden">
                                <SelectValue placeholder="Select a form" class="block truncate text-left" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="form in surveyForms" :key="form.id" :value="form.id.toString()">
                                    <span class="block max-w-[280px] truncate" :title="`${form.title} (${form.code})`"> {{ form.title }} ({{ form.code }}) </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="space-y-3 rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <p class="text-sm text-blue-800 dark:text-blue-200">This will create a survey target for this course and automatically assign it to all enrolled students.</p>
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="closeSurveyDialog">Cancel</Button>
                <Button :disabled="!selectedFormId" @click="handleCreateSurvey">Create Survey</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
