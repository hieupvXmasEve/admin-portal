<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PaginatedResponse } from '@/types';
import type { CourseOffering, Semester } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { BarChart3, Edit, Eye, MoreHorizontal, Plus, ToggleLeft, ToggleRight, Trash2 } from 'lucide-vue-next';
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
}
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Course Offerings',
        href: '/course-offerings',
    },
];

const props = defineProps<Props>();

const filters = ref({
    search: props.filters.search || '',
    semester_id: props.filters.semester_id || 'all',
    enrollment_status: props.filters.enrollment_status || 'all',
    delivery_mode: props.filters.delivery_mode || 'all',
});

const selectedItems = ref<number[]>([]);
const isLoading = ref(false);
const statistics = ref<any>(null);

// Load statistics
const loadStatistics = async () => {
    try {
        const response = await fetch(`/api/course-offerings/statistics?semester_id=${filters.value.semester_id}`);
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

const handlePageChange = (page: string) => {
    router.get(
        '/course-offerings',
        { ...filters.value, page },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
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
    if (confirm('Are you sure you want to delete this course offering?')) {
        router.delete(`/course-offerings/${courseOffering.id}`, {
            onSuccess: () => {
                toast({
                    title: 'Success',
                    description: 'Course offering deleted successfully',
                });
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Failed to delete course offering',
                    variant: 'destructive',
                });
            },
        });
    }
};

const bulkDelete = () => {
    if (selectedItems.value.length === 0) {
        toast({
            title: 'Warning',
            description: 'Please select items to delete',
            variant: 'destructive',
        });
        return;
    }

    if (confirm(`Are you sure you want to delete ${selectedItems.value.length} course offering(s)?`)) {
        router.delete('/api/course-offerings/bulk-delete', {
            data: { ids: selectedItems.value },
            onSuccess: () => {
                selectedItems.value = [];
                toast({
                    title: 'Success',
                    description: 'Course offerings deleted successfully',
                });
            },
            onError: () => {
                toast({
                    title: 'Error',
                    description: 'Failed to delete course offerings',
                    variant: 'destructive',
                });
            },
        });
    }
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
    {
        accessorKey: 'unit',
        header: 'Unit',
        cell: ({ row }) => {
            const course = row.original;
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'font-medium' }, course.unit?.code || 'N/A'),
                course.section_code && h('div', { class: 'text-sm text-muted-foreground' }, `Section: ${course.section_code}`),
            ]);
        },
    },
    {
        accessorKey: 'unit.name',
        header: 'Unit Name',
        cell: ({ row }) => {
            const course = row.original;
            return h('div', { class: 'max-w-xs' }, [
                h('div', { class: 'font-medium truncate' }, course.unit?.name || 'N/A'),
                course.unit?.credit_points && h('div', { class: 'text-sm text-muted-foreground' }, `${course.unit.credit_points} credits`),
            ]);
        },
    },
    {
        accessorKey: 'semester',
        header: 'Semester',
        cell: ({ row }) => {
            const semester = row.original.semester;
            return semester
                ? h('div', {}, [
                      h('div', { class: 'font-medium' }, semester.name),
                      h('div', { class: 'text-sm text-muted-foreground' }, semester.code),
                  ])
                : 'N/A';
        },
    },
    {
        accessorKey: 'enrollment',
        header: 'Enrollment',
        cell: ({ row }) => {
            const course = row.original;
            const percentage = course.max_capacity > 0 ? Math.round((course.current_enrollment / course.max_capacity) * 100) : 0;
            return h('div', { class: 'text-center' }, [
                h('div', { class: 'font-medium' }, `${course.current_enrollment}/${course.max_capacity}`),
                h('div', { class: 'text-sm text-muted-foreground' }, `${percentage}%`),
            ]);
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
        accessorKey: 'instructor',
        header: 'Instructor',
        cell: ({ row }) => {
            const instructor = row.original.instructor;
            return instructor ? instructor.name : 'Not Assigned';
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
                        h(DropdownMenuTrigger, { asChild: true }, () =>
                            h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () => h(MoreHorizontal, { class: 'h-4 w-4' })),
                        ),
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
                                () => [
                                    course.enrollment_status === 'open'
                                        ? h(ToggleLeft, { class: 'mr-2 h-4 w-4' })
                                        : h(ToggleRight, { class: 'mr-2 h-4 w-4' }),
                                    course.enrollment_status === 'open' ? 'Close Registration' : 'Open Registration',
                                ],
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
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="h-full space-y-4 p-4">
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
                    <DataTable :data="courseOfferings.data" :columns="columns" :loading="isLoading" v-model:selected="selectedItems" row-key="id" />
                </CardContent>
            </Card>

            <!-- Pagination -->
            <DataPagination :pagination-data="courseOfferings" @navigate="handlePageChange" />
        </div>
    </AppLayout>
</template>
