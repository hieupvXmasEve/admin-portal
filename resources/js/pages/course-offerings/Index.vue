<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDataTable } from '@/composables/useDataTable';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import type { PaginatedResponse } from '@/types';
import type { CourseOffering } from '@/types/models';
import { courseRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { Copy, Edit, Eye, MoreHorizontal, Plus, Trash2 } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface CourseOfferingFilters {
    search: string;
    module_id: string;
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

interface FilterOption {
    value: string;
    label: string;
}

interface CourseOfferingStatistics {
    total_offerings: number;
    active_offerings: number;
}

interface Props {
    courseOfferings: PaginatedResponse<CourseOffering>;
    filters: CourseOfferingFilters;
    statistics: CourseOfferingStatistics;
    moduleOptions: FilterOption[];
    unitLevels: FilterOption[];
    unitTypes: FilterOption[];
    enrollmentStatusOptions: FilterOption[];
    courseStatusOptions: FilterOption[];
    deliveryModeOptions: FilterOption[];
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}
const props = defineProps<Props>();

const { filters, setFilter, handleSearch, handlePaginationNavigate, handlePageSizeChange, handleSortChange, isLoading, currentSort, currentDirection } = useDataTable<CourseOfferingFilters>({
    baseUrl: route('course-offerings.index'),
    initialFilters: {
        search: props.filters.search || '',
        module_id: props.filters.module_id || 'all',
        enrollment_status: props.filters.enrollment_status || 'all',
        course_status: props.filters.course_status || 'all',
        delivery_mode: props.filters.delivery_mode || 'all',
        unit_level: props.filters.unit_level || 'all',
        unit_type: props.filters.unit_type || 'all',
        page: props.filters.page || 1,
        per_page: props.filters.per_page || 15,
        sort: props.filters.sort || 'unit_code',
        direction: props.filters.direction || 'asc',
    },
    only: ['courseOfferings', 'filters', 'statistics', 'moduleOptions'],
    defaultValues: {
        search: '',
        module_id: 'all',
        enrollment_status: 'all',
        course_status: 'all',
        delivery_mode: 'all',
        unit_level: 'all',
        unit_type: 'all',
        page: 1,
        per_page: 15,
        sort: 'unit_code',
        direction: 'asc',
    },
    debounce: 300,
    immediateFields: ['module_id', 'enrollment_status', 'course_status', 'delivery_mode', 'unit_level', 'unit_type', 'per_page', 'page'],
});

const selectedItems = ref<number[]>([]);

const handleSelectionChange = (selectedRows: CourseOffering[]) => {
    selectedItems.value = selectedRows.map((row) => row.id);
};

// Initialize the confirm dialog composable
const confirmDialog = useGlobalConfirmDialog();

const deleteCourseOffering = (courseOffering: CourseOffering) => {
    const unitName = courseOffering.unit?.name || 'Unknown Unit';
    const unitCode = courseOffering.unit?.code || '';
    const itemName = unitCode ? `${unitCode} - ${unitName}` : unitName;

    confirmDialog.confirmDelete(itemName, 'course offering', () => {
        return new Promise((resolve, reject) => {
            router.delete(route('course-offerings.destroy', courseOffering.id), {
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
                        route('course-offerings.duplicate', courseOffering.id),
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
                    router.delete(route('api.course-offerings.bulk-delete'), {
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

// ---- Lifecycle badge helper ----
const getLifecycleBadge = (course: CourseOffering): { label: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' } => {
    const courseStatus = course.course_status || 'not_started';
    const enrollmentStatus = course.enrollment_status || 'closed';

    if (courseStatus === 'cancelled') return { label: 'Cancelled', variant: 'destructive' };
    if (courseStatus === 'completed') return { label: 'Completed', variant: 'outline' };
    if (courseStatus === 'in_progress') return { label: 'Teaching', variant: 'secondary' };
    // not_started
    if (enrollmentStatus === 'open') return { label: 'Enrolling', variant: 'default' };
    return { label: 'Scheduled', variant: 'secondary' };
};

// Table columns definition
const columns: ColumnDef<CourseOffering>[] = [
    // No number
    {
        id: 'number',
        header: 'No.',
        enableSorting: false,
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.index + 1 + (props.courseOfferings.current_page - 1) * props.courseOfferings.per_page);
        },
    },
    {
        id: 'unit_code',
        accessorKey: 'unit.code',
        header: 'Unit',
        cell: ({ row }) => {
            const course = row.original;
            const unitCode = course.unit?.code || 'N/A';
            const modules = course.unit?.modules ?? [];

            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'font-medium' }, unitCode),
                course.section_code && h('div', { class: 'text-sm text-muted-foreground' }, `Section: ${course.section_code}`),
                modules.length > 0 &&
                    h(
                        'div',
                        { class: 'flex flex-wrap gap-1' },
                        modules.map((module) => h(Badge, { variant: 'secondary', class: 'text-xs font-normal' }, () => module.name)),
                    ),
            ]);
        },
    },
    {
        id: 'unit_name',
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
        id: 'unit_level',
        accessorKey: 'unit.level',
        header: 'Level',
        cell: ({ row }) => {
            const course = row.original;
            const level = course.unit?.level ?? 0;
            return h('div', { class: 'text-left font-medium' }, `Level ${level}`);
        },
    },
    {
        id: 'unit_type',
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
        enableSorting: false,
        cell: ({ row }) => {
            const semester = row.original.semester;
            return semester ? h('div', {}, [h('div', { class: 'font-medium' }, semester.name), h('div', { class: 'text-sm text-muted-foreground' }, semester.code)]) : 'N/A';
        },
    },
    {
        id: 'current_enrollment',
        accessorKey: 'current_enrollment',
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
        id: 'course_status',
        accessorKey: 'course_status',
        header: 'Lifecycle',
        cell: ({ row }) => {
            const badge = getLifecycleBadge(row.original);
            return h(Badge, { variant: badge.variant }, () => badge.label);
        },
    },
    {
        accessorKey: 'lecture',
        header: 'Lecture',
        enableSorting: false,
        cell: ({ row }) => {
            const lecture = row.original.lecture;

            return lecture ? lecture.display_name : 'Not Assigned';
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enablePinning: true,
        enableSorting: false,
        cell: ({ row }) => {
            const course = row.original;
            const courseStatus = course.course_status || 'not_started';
            const isCompleted = courseStatus === 'completed';
            const isCancelled = courseStatus === 'cancelled';
            const canModify = !isCompleted && !isCancelled;

            // Mutation-only dropdown items
            const menuItems: any[] = [h(DropdownMenuItem, { onClick: () => duplicateCourseOffering(course) }, () => [h(Copy, { class: 'mr-2 h-4 w-4' }), 'Duplicate'])];

            if (canModify) {
                menuItems.push(
                    h(DropdownMenuItem, { onClick: () => router.visit(route('course-offerings.edit', course.id)) }, () => [h(Edit, { class: 'mr-2 h-4 w-4' }), 'Edit']),
                    h(DropdownMenuItem, { onClick: () => deleteCourseOffering(course), class: 'text-destructive' }, () => [h(Trash2, { class: 'mr-2 h-4 w-4' }), 'Delete']),
                );
            }

            // Eye icon (always visible, outside dropdown)
            const eyeButton = h(
                Button,
                {
                    variant: 'ghost',
                    size: 'sm',
                    title: 'View Details',
                    onClick: (e: Event) => {
                        e.stopPropagation();
                        router.visit(route('course-offerings.show', course.id));
                    },
                },
                () => h(Eye, { class: 'h-4 w-4' }),
            );

            const dropdownMenu = h(
                DropdownMenu,
                {},
                {
                    default: () => [h(DropdownMenuTrigger, { asChild: true }, () => h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () => h(MoreHorizontal, { class: 'h-4 w-4' }))), h(DropdownMenuContent, { align: 'end' }, () => menuItems)],
                },
            );

            return h('div', { class: 'flex items-center gap-1' }, [eyeButton, dropdownMenu]);
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
            <Link :href="courseRoutes.offerings.create()">
                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Create Course Offering
                </Button>
            </Link>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid gap-4 md:grid-cols-2">
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
    </div>

    <!-- Filters -->
    <Card>
        <CardHeader>
            <CardTitle>Filters</CardTitle>
            <CardDescription>Filter course offerings by various criteria</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Search</label>
                    <DebouncedInput v-model="filters.search" @update:model-value="handleSearch" placeholder="Search courses..." :debounce="300" />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Module</label>
                    <Select :model-value="filters.module_id" :disabled="isLoading" @update:model-value="(value) => setFilter('module_id', String(value))">
                        <SelectTrigger>
                            <SelectValue placeholder="All Modules" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Modules</SelectItem>
                            <SelectItem v-for="module in moduleOptions" :key="module.value" :value="module.value">
                                {{ module.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Level</label>
                    <Select :model-value="filters.unit_level" :disabled="isLoading" @update:model-value="(value) => setFilter('unit_level', String(value))">
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
                    <Select :model-value="filters.unit_type" :disabled="isLoading" @update:model-value="(value) => setFilter('unit_type', String(value))">
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
                    <Select :model-value="filters.course_status" :disabled="isLoading" @update:model-value="(value) => setFilter('course_status', String(value))">
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
                    <Select :model-value="filters.delivery_mode" :disabled="isLoading" @update:model-value="(value) => setFilter('delivery_mode', String(value))">
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
            <DataTable
                :data="courseOfferings.data"
                :columns="columns"
                :loading="isLoading"
                :initial-sort="currentSort ?? undefined"
                :initial-direction="currentDirection ?? undefined"
                :enable-row-selection="true"
                @selection-change="handleSelectionChange"
                @sort-change="handleSortChange"
            />
        </CardContent>
    </Card>

    <!-- Pagination -->
    <DataPagination :pagination-data="courseOfferings" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
