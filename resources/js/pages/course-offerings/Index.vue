<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Tooltip from '@/components/ui/tooltip/Tooltip.vue';
import TooltipContent from '@/components/ui/tooltip/TooltipContent.vue';
import TooltipProvider from '@/components/ui/tooltip/TooltipProvider.vue';
import TooltipTrigger from '@/components/ui/tooltip/TooltipTrigger.vue';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import type { PaginatedResponse } from '@/types';
import type { CourseOffering, Semester } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { BarChart3, Copy, Edit, Eye, MoreHorizontal, Plus, ToggleLeft, ToggleRight, Trash2 } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    courseOfferings: PaginatedResponse<CourseOffering>;
    filters: {
        search?: string;
        semester_id?: string;
        enrollment_status?: string;
        delivery_mode?: string;
    };
    semesters: Semester[];
    enrollmentStatusOptions: { value: string; label: string }[];
    deliveryModeOptions: { value: string; label: string }[];
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
}
const props = defineProps<Props>();
console.log(props.courseOfferings);
const filters = ref({
    search: props.filters.search || '',
    semester_id: props.filters.semester_id || 'all',
    enrollment_status: props.filters.enrollment_status || 'all',
    delivery_mode: props.filters.delivery_mode || 'all',
});

const selectedItems = ref<number[]>([]);
const isLoading = ref(false);
const statistics = ref<any>(null);

// Initialize the confirm dialog composable
const confirmDialog = useGlobalConfirmDialog();

// Load statistics
const loadStatistics = async () => {
    try {
        const semesterId = filters.value.semester_id === 'all' ? '' : filters.value.semester_id;
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

// Load statistics on mount and when semester changes
loadStatistics();

const updateFilters = () => {
    const filterParams = {
        search: filters.value.search || undefined,
        semester_id: filters.value.semester_id === 'all' ? undefined : filters.value.semester_id,
        enrollment_status: filters.value.enrollment_status === 'all' ? undefined : filters.value.enrollment_status,
        delivery_mode: filters.value.delivery_mode === 'all' ? undefined : filters.value.delivery_mode,
    };

    router.get('/course-offerings', filterParams, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => loadStatistics(),
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    updateFilters();
};

const handlePageChange = (pageOrUrl: string) => {
    // If it's a full URL, extract the page number from it
    if (pageOrUrl.includes('http') || pageOrUrl.includes('?')) {
        try {
            const url = new URL(pageOrUrl, window.location.origin);
            const page = url.searchParams.get('page') || '1';

            const filterParams = {
                search: filters.value.search || undefined,
                semester_id: filters.value.semester_id === 'all' ? undefined : filters.value.semester_id,
                enrollment_status: filters.value.enrollment_status === 'all' ? undefined : filters.value.enrollment_status,
                delivery_mode: filters.value.delivery_mode === 'all' ? undefined : filters.value.delivery_mode,
                page: page,
            };

            router.get('/course-offerings', filterParams, {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => loadStatistics(),
            });
        } catch (error) {
            console.error('Error parsing URL:', error);
        }
    } else {
        // If it's just a page number, construct the request with current filters
        const filterParams = {
            search: filters.value.search || undefined,
            semester_id: filters.value.semester_id === 'all' ? undefined : filters.value.semester_id,
            enrollment_status: filters.value.enrollment_status === 'all' ? undefined : filters.value.enrollment_status,
            delivery_mode: filters.value.delivery_mode === 'all' ? undefined : filters.value.delivery_mode,
            page: pageOrUrl,
        };

        router.get('/course-offerings', filterParams, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => loadStatistics(),
        });
    }
};

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
                onSuccess: () => {
                    toast.success('Course offering deleted successfully');
                    resolve();
                },
                onError: (errors) => {
                    console.error('Failed to delete course offering:', errors);
                    reject(new Error('Failed to delete course offering'));
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
                        onSuccess: () => {
                            selectedItems.value = [];
                            resolve();
                        },
                        onError: (errors) => {
                            console.error('Failed to delete course offerings:', errors);
                            reject(new Error('Failed to delete course offerings'));
                        },
                    });
                });
            },
        },
    );
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'open':
            return 'default';
        case 'waitlist_only':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        case 'closed':
            return 'outline';
        default:
            return 'outline';
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
        accessorKey: 'semester',
        header: 'Semester',
        cell: ({ row }) => {
            const semester = row.original.semester;
            return semester ? h('div', {}, [h('div', { class: 'font-medium' }, semester.name), h('div', { class: 'text-sm text-muted-foreground' }, semester.code)]) : 'N/A';
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
        accessorKey: 'enrollment_status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.enrollment_status;
            if (!status) return h('span', {}, 'Unknown');
            return h(Badge, { variant: getStatusBadgeVariant(status) }, () => status.replace('_', ' ').toUpperCase());
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
        cell: ({ row }) => {
            const course = row.original;
            return h(
                DropdownMenu,
                {},
                {
                    default: () => [
                        h(DropdownMenuTrigger, { asChild: true }, () => h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () => h(MoreHorizontal, { class: 'h-4 w-4' }))),
                        h(DropdownMenuContent, { align: 'end' }, () => [
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
                                    onClick: () => router.visit(`/course-offerings/${course.id}/edit`),
                                },
                                () => [h(Edit, { class: 'mr-2 h-4 w-4' }), 'Edit'],
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
                                    onClick: () => duplicateCourseOffering(course),
                                },
                                () => [h(Copy, { class: 'mr-2 h-4 w-4' }), 'Duplicate'],
                            ),
                            h(
                                DropdownMenuItem,
                                {
                                    onClick: () => deleteCourseOffering(course),
                                    class: 'text-destructive',
                                },
                                () => [h(Trash2, { class: 'mr-2 h-4 w-4' }), 'Delete'],
                            ),
                        ]),
                    ],
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
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Search</label>
                    <DebouncedInput v-model="filters.search" @debounced="handleSearch" placeholder="Search courses..." :debounce="300" />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Semester</label>
                    <Select v-model="filters.semester_id" @update:model-value="updateFilters">
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
                    <label class="text-sm font-medium">Status</label>
                    <Select v-model="filters.enrollment_status" @update:model-value="updateFilters">
                        <SelectTrigger>
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem v-for="option in enrollmentStatusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Delivery Mode</label>
                    <Select v-model="filters.delivery_mode" @update:model-value="updateFilters">
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
            <DataTable :data="courseOfferings.data" :columns="columns" :loading="isLoading" v-model:selected="selectedItems" row-key="id">
                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="router.visit(`/course-offerings/${row.original.id}`)" title="View course offering">
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>View course offering</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="router.visit(`/course-offerings/${row.original.id}/edit`)" title="Edit course offering">
                                        <Edit class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Edit course offering</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="toggleStatus(row.original)" title="Toggle course offering status">
                                        <ToggleRight class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{{ row.original.enrollment_status === 'open' ? 'Close Registration' : 'Open Registration' }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="duplicateCourseOffering(row.original)" title="Duplicate course offering">
                                        <Copy class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Duplicate course offering</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                        <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="sm" @click="deleteCourseOffering(row.original)" title="Delete program">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Delete course offering</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </template>
            </DataTable>
        </CardContent>
    </Card>

    <!-- Pagination -->
    <DataPagination :pagination-data="courseOfferings" @navigate="handlePageChange" />
</template>
